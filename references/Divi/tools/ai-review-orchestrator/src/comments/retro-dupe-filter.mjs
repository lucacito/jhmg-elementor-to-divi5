import OpenAI from "openai";

import { RETRO_DUPE_MODEL } from "../core/constants.mjs";
import { log } from "../core/logging.mjs";
import { parseJsonSafe } from "../core/utils.mjs";
import { resolveConventionalMeta } from "./formatting.mjs";

const normalizeFindingKey = (finding) => {
  const title = String(finding?.title || "")
    .trim()
    .toLowerCase()
    .replace(/\s+/g, " ");
  const location = Array.isArray(finding?.locations)
    ? finding.locations[0]?.path ?? ""
    : "";
  const normalizedLocation = location ? location.replace(/^(\.\/)+/, "") : "";
  return `${title}::${normalizedLocation}`.trim();
};

const hasDiffEvidence = (diff, locationPath) => {
  if (null == diff || "" === diff || null == locationPath) {
    return false;
  }
  const normalized = locationPath.replace(/^(\.\/)+/, "");
  return (
    diff.includes(`diff --git a/${normalized} b/${normalized}`) ||
    diff.includes(`b/${normalized}`) ||
    diff.includes(`/${normalized}`)
  );
};

const applyExactDuplicateFilter = ({ retroReview, findings }) => {
  const priorFindings = Array.isArray(retroReview?.prior_findings)
    ? retroReview.prior_findings
    : [];
  const diff = retroReview?.diff_since_last_run || "";
  if (0 === priorFindings.length || "" === diff) {
    return { filtered: findings || [], dropped: [], report: null };
  }
  const priorKeys = new Set(priorFindings.map((entry) => entry.key).filter(Boolean));
  const dropped = [];
  const filtered = [];
  findings.forEach((finding) => {
    const key = normalizeFindingKey(finding);
    const matches = "" !== key && priorKeys.has(key);
    if (false === matches) {
      filtered.push(finding);
      return;
    }
    const locations = Array.isArray(finding?.locations) ? finding.locations : [];
    const hasEvidence = locations.some((location) =>
      hasDiffEvidence(diff, location?.path)
    );
    if (true === hasEvidence) {
      filtered.push(finding);
      return;
    }
    dropped.push(finding);
  });
  return {
    filtered,
    dropped,
    report: {
      reason: "exact_duplicate_without_new_diff",
      dropped_count: dropped.length,
    },
  };
};

const buildRetroDupePrompt = ({ retroReview, findings }) => [
  {
    role: "system",
    content: [
      "You are a dedupe filter for automated PR review findings.",
      "Your job is to remove findings that are true duplicates of prior feedback",
      "that has already been resolved or rebutted by the author.",
      "Only drop findings when the duplication is clear.",
      "When unsure, keep the finding.",
    ].join(" "),
  },
  {
    role: "user",
    content: [
      "Return JSON only with:",
      "{",
      '  "drop_finding_ids": ["finding_1", "..."],',
      '  "notes": "short explanation"',
      "}",
      "",
      "Rules:",
      "- drop_finding_ids should only include findings that are already addressed",
      "  in prior feedback and do not have new evidence in the diff since last run.",
      "- Use Prior Review Feedback to compare against resolved threads and rebuttals.",
      "- Use diff_since_last_run to decide whether new evidence exists.",
      "- If a thread is rebutted by the author and no new diff evidence exists, drop the finding.",
      "- Prefer keeping a finding when it contains new nuance or new evidence.",
      "",
      "Prior Review Feedback (facts.retroReview):",
      JSON.stringify(retroReview || {}, null, 2),
      "",
      "New Findings:",
      JSON.stringify(findings || [], null, 2),
    ].join("\n"),
  },
];

const getResponseText = (response) =>
  response.output_text ||
  response.output?.map((item) => item.content?.[0]?.text ?? "").join("\n") ||
  "";

export const applyRetroDupeFilter = async ({ facts, findings }) => {
  const retroReview = facts?.retroReview || null;
  if (null == retroReview || true !== retroReview.enabled) {
    return { filtered: findings || [], dropped: [], report: null };
  }
  if (false === Array.isArray(findings) || 0 === findings.length) {
    return { filtered: findings || [], dropped: [], report: null };
  }
  const baseline = applyExactDuplicateFilter({ retroReview, findings });
  const baselineReport = baseline.report
    ? { ...baseline.report, dropped_count: baseline.dropped.length }
    : null;
  if (baseline.dropped.length > 0) {
    log(
      `[retro-dupe] exact dedupe dropped ${baseline.dropped.length} finding${
        baseline.dropped.length === 1 ? "" : "s"
      }.`
    );
  }
  const apiKey = process.env.OPENAI_API_KEY;
  if (null == apiKey || "" === String(apiKey).trim()) {
    log("[retro-dupe] warning: OPENAI_API_KEY missing; skipping retro dupe filter.");
    return {
      filtered: baseline.filtered,
      dropped: baseline.dropped,
      report: baselineReport,
    };
  }
  const client = new OpenAI({ apiKey });
  const indexed = baseline.filtered.map((finding, index) => ({
    id: `finding_${index + 1}`,
    finding,
  }));
  const payload = indexed.map(({ id, finding }) => {
    const meta = resolveConventionalMeta(finding);
    return {
      id,
      title: finding?.title || "Finding",
      comment_label: meta.label || null,
      comment_decorations: meta.decorations || [],
      confidence: finding?.confidence ?? null,
      reviewer: finding?.reviewer || null,
      rationale: finding?.rationale || null,
      suggested_fix: finding?.suggested_fix || null,
      locations: Array.isArray(finding?.locations)
        ? finding.locations.map((location) => ({
            path: location?.path ?? null,
            lines: location?.lines ?? null,
            snippet: location?.snippet ?? null,
          }))
        : [],
    };
  });
  try {
    const response = await client.responses.create({
      model: RETRO_DUPE_MODEL,
      input: buildRetroDupePrompt({ retroReview, findings: payload }),
      text: {
        format: {
          type: "json_schema",
          name: "retro_dupe_filter",
          strict: true,
          schema: {
            type: "object",
            properties: {
              drop_finding_ids: {
                type: "array",
                items: { type: "string" },
              },
              notes: { type: "string" },
            },
            required: ["drop_finding_ids", "notes"],
            additionalProperties: false,
          },
        },
      },
    });
    const outputText = getResponseText(response);
    const parsed = parseJsonSafe(outputText);
    const drops = Array.isArray(parsed?.drop_finding_ids)
      ? parsed.drop_finding_ids
      : [];
    if (0 === drops.length) {
      return {
        filtered: baseline.filtered,
        dropped: baseline.dropped,
        report: baselineReport,
      };
    }
    const dropSet = new Set(drops);
    const filtered = indexed
      .filter(({ id }) => false === dropSet.has(id))
      .map(({ finding }) => finding);
    const modelDropped = indexed
      .filter(({ id }) => true === dropSet.has(id))
      .map(({ finding }) => finding);
    const dropped = [...baseline.dropped, ...modelDropped];
    const report = {
      prefilter: baselineReport,
      model: {
        dropped_count: modelDropped.length,
        notes: parsed?.notes ?? null,
      },
    };
    log(
      `[retro-dupe] dropped ${dropped.length} duplicate findings (${modelDropped.length} model, ${baseline.dropped.length} exact).`
    );
    return { filtered, dropped, report };
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    log(`[retro-dupe] warning: failed to apply retro dupe filter. ${message}`);
    return {
      filtered: baseline.filtered,
      dropped: baseline.dropped,
      report: baselineReport,
    };
  }
};
