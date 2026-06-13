import fs from "fs";
import path from "path";
import dotenv from "dotenv";
import OpenAI from "openai";
import { getDefaultDbPath, insertFinding, openDb } from "./db.mjs";

const args = process.argv.slice(2);

const getArgValue = (name) => {
  const index = args.indexOf(name);
  if (-1 === index) {
    return null;
  }
  return args[index + 1] ?? null;
};

const hasArg = (name) => args.includes(name);

const getRepoRoot = () => {
  let current = path.resolve(process.cwd());
  while (current !== path.dirname(current)) {
    if (true === fs.existsSync(path.join(current, ".git"))) {
      return current;
    }
    current = path.dirname(current);
  }
  throw new Error("Could not locate git repo root.");
};

const loadEnv = (repoRoot) => {
  const envPath = path.join(repoRoot, "tools/ai-review-orchestrator/.env");
  if (true === fs.existsSync(envPath)) {
    dotenv.config({ path: envPath });
  }
};

const buildClusterPrompt = ({ reviewerNames, clusterId, members }) => [
  {
    role: "system",
    content:
      "You summarize recurring PR feedback into generalized reviewer guidance.",
  },
  {
    role: "user",
    content: [
      "Given the clustered PR feedback, produce a generalized reviewer update.",
      "Focus on repeatable reviewer wisdom, not PR-specific details.",
      "",
      `Reviewer names: ${reviewerNames.join(", ") || "none"}`,
      `Cluster ID: ${clusterId}`,
      "",
      "Comments (JSON):",
      JSON.stringify(members, null, 2),
      "",
      "Return JSON only with:",
      "{",
      '  "title": string,',
      '  "summary": string,',
      '  "reviewer": string,',
      '  "patch_type": string,',
      '  "section_title": string,',
      '  "operation": "add" | "replace",',
      '  "target_text": string | null,',
      '  "suggestion_text": string,',
      '  "rationale": string,',
      '  "source_comment_ids": number[]',
      "}",
      "",
      "Rules:",
      "- patch_type must be one of: reviewer, rule, docs, other.",
      "- If operation is replace, provide target_text to replace.",
      "- Avoid copy-pasting comment text verbatim.",
    ].join("\n"),
  },
];

const summarizeCluster = async ({ client, model, reviewerNames, clusterId, members }) => {
  const response = await client.responses.create({
    model,
    input: buildClusterPrompt({ reviewerNames, clusterId, members }),
  });
  const outputText =
    response.output_text ||
    response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
    "";
  return outputText.trim();
};

const loadReviewerNames = (repoRoot) => {
  const reviewersDir = path.join(
    repoRoot,
    "tools/ai-review-orchestrator/reviewers"
  );
  if (false === fs.existsSync(reviewersDir)) {
    return [];
  }
  return fs
    .readdirSync(reviewersDir)
    .filter((file) => file.endsWith(".md"))
    .map((file) => file.replace(/\.md$/, ""));
};

const findLatestRunId = (db, repoSlug) =>
  db
    .prepare(
      `
      SELECT id
      FROM cluster_runs
      WHERE repo = ?
      ORDER BY id DESC
      LIMIT 1
    `
    )
    .get(repoSlug)?.id ?? null;

const fetchClusters = (db, { runId, includeSummarized }) =>
  db
    .prepare(
      `
      SELECT id, summary
      FROM clusters
      WHERE run_id = ?
        AND (? = 1 OR summary IS NULL OR summary = '')
      ORDER BY id ASC
    `
    )
    .all(runId, includeSummarized ? 1 : 0);

const fetchClusterMembers = (db, clusterId, limit) =>
  db
    .prepare(
      `
      SELECT
        cm.distance,
        c.id as comment_id,
        c.author,
        c.type,
        c.path,
        c.created_at,
        c.body
      FROM cluster_members cm
      INNER JOIN comments c ON c.id = cm.comment_id
      WHERE cm.cluster_id = ?
      ORDER BY cm.distance ASC
      LIMIT ?
    `
    )
    .all(clusterId, limit);

const updateClusterSummary = (db, { clusterId, title, summary, reviewer, suggestionJson }) => {
  db.prepare(
    `
      UPDATE clusters
      SET title = ?,
          summary = ?,
          reviewer = ?,
          suggestion_json = ?
      WHERE id = ?
    `
  ).run(title, summary, reviewer, suggestionJson, clusterId);
};

const main = async () => {
  const repoRoot = getRepoRoot();
  loadEnv(repoRoot);

  const repoSlug = getArgValue("--repo") || "elegantthemes/submodule-builder-5";
  const runIdValue = getArgValue("--run-id");
  const runId = null == runIdValue ? null : Number(runIdValue);
  const includeSummarized = true === hasArg("--all");
  const maxCommentsValue = getArgValue("--max-comments");
  const maxComments = null == maxCommentsValue ? 20 : Number(maxCommentsValue);
  const analysisModel =
    getArgValue("--analysis-model") ||
    process.env.OPENAI_SUMMARY_MODEL ||
    "gpt-5.3-codex";

  if (true === Number.isNaN(runId)) {
    throw new Error("Invalid --run-id value.");
  }
  if (true === Number.isNaN(maxComments)) {
    throw new Error("Invalid --max-comments value.");
  }

  const dbArg = getArgValue("--db");
  const dbContext = openDb({ repoRoot, dbPath: dbArg });
  const db = dbContext.db;
  console.log(`db: ${dbContext.dbPath ?? getDefaultDbPath(repoRoot)}`);

  const resolvedRunId = runId ?? findLatestRunId(db, repoSlug);
  if (null == resolvedRunId) {
    throw new Error("No cluster runs found for repo.");
  }

  const clusters = fetchClusters(db, {
    runId: resolvedRunId,
    includeSummarized,
  });
  const reviewerNames = loadReviewerNames(repoRoot);
  const client = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });

  let summarized = 0;
  let findingsInserted = 0;

  for (const cluster of clusters) {
    const members = fetchClusterMembers(db, cluster.id, maxComments);
    if (0 === members.length) {
      continue;
    }
    const summaryText = await summarizeCluster({
      client,
      model: analysisModel,
      reviewerNames,
      clusterId: cluster.id,
      members,
    });
    let summaryJson = null;
    try {
      summaryJson = JSON.parse(summaryText);
    } catch (error) {
      summaryJson = null;
    }

    if (!summaryJson) {
      continue;
    }

    const suggestionJson = JSON.stringify(summaryJson);
    updateClusterSummary(db, {
      clusterId: cluster.id,
      title: summaryJson.title ?? null,
      summary: summaryJson.summary ?? null,
      reviewer: summaryJson.reviewer ?? null,
      suggestionJson,
    });

    insertFinding(db, {
      comment_id: null,
      cluster_id: cluster.id,
      reviewer: summaryJson.reviewer ?? "unknown",
      patch_type: summaryJson.patch_type ?? "reviewer",
      operation: summaryJson.operation ?? "add",
      section_title: summaryJson.section_title ?? null,
      target_text: summaryJson.target_text ?? null,
      suggestion_text: summaryJson.suggestion_text ?? "",
      raw_json: suggestionJson,
      created_at: new Date().toISOString(),
    });

    summarized += 1;
    findingsInserted += 1;
  }

  console.log(
    JSON.stringify(
      {
        repo: repoSlug,
        run_id: Number(resolvedRunId),
        summarized,
        findings_inserted: findingsInserted,
      },
      null,
      2
    )
  );
};

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
