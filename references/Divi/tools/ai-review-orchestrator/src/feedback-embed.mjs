import fs from "fs";
import path from "path";
import dotenv from "dotenv";
import OpenAI from "openai";
import { getDefaultDbPath, openDb } from "./db.mjs";

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

const parseDate = (value) => {
  if (null == value || "" === value) {
    return null;
  }
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) {
    throw new Error(`Invalid date: ${value}`);
  }
  return parsed;
};

const SKIP_COMMENT_REGEX = [
  /deephive test failure summary/i,
  /deephive automated analysis/i,
  /\blgtm\b/i,
  /\bclos(e|ing)\s+(this|the)\s+pr\b/i,
  /\bfixed in\b/i,
  /\bi'?ve addressed\b/i,
  /\bfix eslint errors?\b/i,
  /\bfix snapshot\b/i,
];

const shouldSkipByRegex = (body) =>
  SKIP_COMMENT_REGEX.some((pattern) => pattern.test(body));

const NANO_MAX_CHARS = 6000;
const EMBED_MAX_CHARS = 6000;

const truncateForNano = (body) => {
  if (body.length <= NANO_MAX_CHARS) {
    return body;
  }
  return `${body.slice(0, NANO_MAX_CHARS)}\n\n[truncated]`;
};

const truncateForEmbedding = (body) => {
  if (body.length <= EMBED_MAX_CHARS) {
    return body;
  }
  return body.slice(0, EMBED_MAX_CHARS);
};

const buildNanoPrompt = (commentBody) => [
  {
    role: "system",
    content:
      "You decide whether a PR comment is substantive feedback worth learning from.",
  },
  {
    role: "user",
    content: [
      "Answer with JSON only: { keep: boolean, reason: string }.",
      "",
      "Guidance:",
      "- keep=true for actionable review feedback, architectural concerns, or requests to change code behavior.",
      "- keep=false for status updates, acknowledgements, test bot summaries, or replies that only describe what changed.",
      "",
      "Comment:",
      truncateForNano(commentBody),
    ].join("\n"),
  },
];

const shouldKeepByNano = async ({ client, model, commentBody }) => {
  const response = await client.responses.create({
    model,
    input: buildNanoPrompt(commentBody),
  });
  const outputText =
    response.output_text ||
    response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
    "";
  try {
    const parsed = JSON.parse(outputText.trim());
    return {
      keep: true === parsed.keep,
      reason: parsed.reason || "",
    };
  } catch (error) {
    return { keep: true, reason: "" };
  }
};

const fetchComments = ({
  db,
  repoSlug,
  since,
  until,
  model,
  dimensions,
  onlyNew,
  limit,
}) => {
  const joinParams = [];
  const whereParams = [];
  let where = "WHERE prs.repo = ?";
  whereParams.push(repoSlug);

  if (since) {
    where += " AND prs.closed_at >= ?";
    whereParams.push(since.toISOString());
  }
  if (until) {
    where += " AND prs.closed_at <= ?";
    whereParams.push(until.toISOString());
  }

  let join = `
    LEFT JOIN comment_filters cf ON cf.comment_id = comments.id
  `;
  where += " AND (cf.status IS NULL OR cf.status != 'skipped')";
  if (true === onlyNew) {
    join += `
      LEFT JOIN comment_embeddings ce
        ON ce.comment_id = comments.id
       AND ce.model = ?
       AND ce.dimensions = ?
    `;
    joinParams.push(model, dimensions);
    where += " AND ce.id IS NULL";
  }

  let limitSql = "";
  const limitParams = [];
  if (null != limit) {
    limitSql = "LIMIT ?";
    limitParams.push(limit);
  }

  const params = [...joinParams, ...whereParams, ...limitParams];

  return db.prepare(`
    SELECT
      comments.id,
      comments.body,
      comments.created_at as comment_created_at,
      cf.status as filter_status,
      cf.reason as filter_reason,
      prs.number as pr_number,
      prs.title as pr_title,
      prs.url as pr_url
    FROM comments
    INNER JOIN prs ON prs.id = comments.pr_id
    ${join}
    ${where}
    ORDER BY prs.closed_at DESC, comments.created_at ASC
    ${limitSql}
  `).all(...params);
};

const upsertCommentFilter = (db, { commentId, status, reason, model }) => {
  db.prepare(`
    INSERT INTO comment_filters (
      comment_id,
      status,
      reason,
      model,
      created_at
    ) VALUES (
      @comment_id,
      @status,
      @reason,
      @model,
      @created_at
    )
    ON CONFLICT(comment_id) DO UPDATE SET
      status = excluded.status,
      reason = excluded.reason,
      model = excluded.model,
      created_at = excluded.created_at
  `).run({
    comment_id: commentId,
    status,
    reason,
    model,
    created_at: new Date().toISOString(),
  });
};

const upsertEmbedding = (db, { commentId, model, dimensions, embedding }) => {
  db.prepare(`
    INSERT INTO comment_embeddings (
      comment_id,
      model,
      dimensions,
      embedding,
      created_at
    ) VALUES (
      @comment_id,
      @model,
      @dimensions,
      @embedding,
      @created_at
    )
    ON CONFLICT(comment_id, model, dimensions) DO UPDATE SET
      embedding = excluded.embedding,
      created_at = excluded.created_at
  `).run({
    comment_id: commentId,
    model,
    dimensions,
    embedding,
    created_at: new Date().toISOString(),
  });
};

const toEmbeddingBlob = (embedding) => {
  const array = Float32Array.from(embedding);
  return Buffer.from(array.buffer);
};

const main = async () => {
  const repoRoot = getRepoRoot();
  loadEnv(repoRoot);

  const repoSlug = getArgValue("--repo") || "elegantthemes/submodule-builder-5";
  const since = parseDate(getArgValue("--since"));
  const until = parseDate(getArgValue("--until"));
  const model =
    getArgValue("--model") ||
    process.env.OPENAI_EMBEDDING_MODEL ||
    "text-embedding-3-small";
  const dimensionsValue = getArgValue("--dimensions");
  const dimensions =
    null == dimensionsValue ? null : Number(dimensionsValue);
  const onlyNew = false === hasArg("--all");
  const limitValue = getArgValue("--limit");
  const limit = null == limitValue ? null : Number(limitValue);
  const progressEveryValue = getArgValue("--progress-every");
  const progressEvery = null == progressEveryValue
    ? 10
    : Number(progressEveryValue);
  const useNanoFilter = false === hasArg("--no-nano-filter");
  const nanoModel =
    getArgValue("--nano-model") ||
    process.env.OPENAI_NANO_MODEL ||
    "gpt-5-nano";
  const minCommentLengthValue = getArgValue("--min-comment-length");
  const minCommentLength =
    null == minCommentLengthValue ? 20 : Number(minCommentLengthValue);

  if (true === Number.isNaN(dimensions)) {
    throw new Error("Invalid --dimensions value.");
  }
  if (true === Number.isNaN(limit)) {
    throw new Error("Invalid --limit value.");
  }
  if (true === Number.isNaN(progressEvery)) {
    throw new Error("Invalid --progress-every value.");
  }
  if (true === Number.isNaN(minCommentLength)) {
    throw new Error("Invalid --min-comment-length value.");
  }

  const dbArg = getArgValue("--db");
  const dbContext = openDb({ repoRoot, dbPath: dbArg });
  const db = dbContext.db;

  console.log(`db: ${dbContext.dbPath ?? getDefaultDbPath(repoRoot)}`);
  console.log(`model: ${model}`);
  console.log(`nano: ${useNanoFilter ? nanoModel : "disabled"}`);

  const rows = fetchComments({
    db,
    repoSlug,
    since,
    until,
    model,
    dimensions,
    onlyNew,
    limit,
  });
  console.log(`comments: ${rows.length}`);

  const client = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });
  let skippedByRegex = 0;
  let skippedByNano = 0;
  let skippedByLength = 0;
  let skippedByFilter = 0;
  let keptByFilter = 0;
  let embedded = 0;
  let processed = 0;

  for (const row of rows) {
    processed += 1;
    const body = row.body?.trim() ?? "";
    if ("skipped" === row.filter_status) {
      skippedByFilter += 1;
      continue;
    }
    if ("kept" === row.filter_status) {
      keptByFilter += 1;
    }
    if (body.length < minCommentLength) {
      skippedByLength += 1;
      upsertCommentFilter(db, {
        commentId: row.id,
        status: "skipped",
        reason: "min_length",
        model: useNanoFilter ? nanoModel : null,
      });
      continue;
    }
    if ("kept" !== row.filter_status) {
      if (true === shouldSkipByRegex(body)) {
        skippedByRegex += 1;
        upsertCommentFilter(db, {
          commentId: row.id,
          status: "skipped",
          reason: "regex",
          model: useNanoFilter ? nanoModel : null,
        });
        continue;
      }
      if (true === useNanoFilter) {
        const keepResult = await shouldKeepByNano({
          client,
          model: nanoModel,
          commentBody: body,
        });
        if (false === keepResult.keep) {
          skippedByNano += 1;
          upsertCommentFilter(db, {
            commentId: row.id,
            status: "skipped",
            reason: keepResult.reason || "nano",
            model: nanoModel,
          });
          continue;
        }
        upsertCommentFilter(db, {
          commentId: row.id,
          status: "kept",
          reason: keepResult.reason || null,
          model: nanoModel,
        });
      } else {
        upsertCommentFilter(db, {
          commentId: row.id,
          status: "kept",
          reason: null,
          model: null,
        });
      }
    }

    const embeddingResponse = await client.embeddings.create({
      model,
      input: truncateForEmbedding(body),
      encoding_format: "float",
      ...(dimensions ? { dimensions } : {}),
    });
    const embedding = embeddingResponse?.data?.[0]?.embedding;
    if (!Array.isArray(embedding)) {
      throw new Error("Embedding response missing vector.");
    }
    const resolvedDimensions = dimensions ?? embedding.length;
    if (embedding.length !== resolvedDimensions) {
      throw new Error(
        `Embedding length ${embedding.length} does not match ${resolvedDimensions}.`
      );
    }
    upsertEmbedding(db, {
      commentId: row.id,
      model,
      dimensions: resolvedDimensions,
      embedding: toEmbeddingBlob(embedding),
    });
    embedded += 1;
    if (0 < progressEvery && 0 === processed % progressEvery) {
      console.log(`progress: ${processed}/${rows.length} processed`);
    }
  }

  console.log(
    JSON.stringify(
      {
        repo: repoSlug,
        model,
        dimensions: dimensions ?? "default",
        processed,
        embedded,
        skipped_by_regex: skippedByRegex,
        skipped_by_nano: skippedByNano,
        skipped_by_length: skippedByLength,
        skipped_by_filter: skippedByFilter,
        kept_by_filter: keptByFilter,
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
