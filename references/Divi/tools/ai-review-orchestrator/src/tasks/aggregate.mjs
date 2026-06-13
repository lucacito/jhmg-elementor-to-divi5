import path from "node:path";

import { task } from "@langchain/langgraph";

import { readJson, writeJson, writeText } from "../core/io.mjs";
import { log } from "../core/logging.mjs";
import { loadReviewerDefinitions } from "../reviewers/loaders.mjs";
import {
  buildFindingKey,
  buildInlineComments,
} from "../comments/inline-comments.mjs";
import {
  buildConventionalHeaderFromFinding,
  resolveConventionalMeta,
} from "../comments/formatting.mjs";
import { applyRetroDupeFilter } from "../comments/retro-dupe-filter.mjs";
import { applyRetroActions } from "../comments/retro-actions.mjs";

const labelOrder = {
  issue_blocking: 5,
  issue_non_blocking: 4,
  suggestion: 3,
  question: 2,
  note: 1,
  nitpick: 0,
  other: 1,
};

const getFindingBucket = (finding) => {
  const meta = resolveConventionalMeta(finding);
  if ("issue" === meta.label) {
    return meta.decorations.includes("blocking")
      ? "issue_blocking"
      : "issue_non_blocking";
  }
  if ("suggestion" === meta.label) {
    return "suggestion";
  }
  if ("question" === meta.label) {
    return "question";
  }
  if ("note" === meta.label) {
    return "note";
  }
  if ("nitpick" === meta.label) {
    return "nitpick";
  }
  return "other";
};

const normalizeLocationPath = (locationPath) => {
  if (null === locationPath) {
    return null;
  }
  const normalized = path.normalize(locationPath);
  return normalized.replace(/^[.][\\/]/, "");
};

const filterFindingLocations = (finding, validPaths) => {
  const locations = Array.isArray(finding.locations) ? finding.locations : null;
  if (null === locations) {
    return finding;
  }
  const droppedPaths = new Set();
  const filteredLocations = locations
    .map((location) => {
      const locationPath = normalizeLocationPath(location?.path ?? null);
      if (null === locationPath) {
        return null;
      }
      if (true === validPaths.has(locationPath)) {
        return { ...location, path: locationPath };
      }
      droppedPaths.add(locationPath);
      return null;
    })
    .filter(Boolean);
  if (0 === filteredLocations.length) {
    if (0 < droppedPaths.size) {
      log(
        `[aggregate] dropped finding outside diff: ${finding?.title || "Finding"} -> ${[
          ...droppedPaths,
        ].join(", ")}`
      );
    }
    return null;
  }
  return { ...finding, locations: filteredLocations };
};

const applyConfidenceRules = (finding, thresholds) => {
  if (null == thresholds) {
    return finding;
  }
  const confidence = Number(finding.confidence ?? 0);
  if (confidence < thresholds.drop_below) {
    return null;
  }
  const blockingMin = thresholds.blocking_min ?? thresholds.blocker_min ?? 0.9;
  const nonBlockingMin =
    thresholds.non_blocking_min ?? thresholds.concern_min ?? 0.75;
  const suggestionMin =
    thresholds.suggestion_min ?? thresholds.concern_min ?? nonBlockingMin;
  const updated = { ...finding };
  const meta = resolveConventionalMeta(updated);
  if ("issue" === meta.label && meta.decorations.includes("blocking")) {
    if (confidence < blockingMin) {
      updated.comment_label = "issue";
      updated.comment_decorations = ["non-blocking"];
    }
    return updated;
  }
  if ("issue" === meta.label && confidence < nonBlockingMin) {
    return null;
  }
  if ("suggestion" === meta.label && confidence < suggestionMin) {
    return null;
  }
  if ("issue" !== meta.label && "suggestion" !== meta.label) {
    if (confidence < nonBlockingMin) {
      return null;
    }
  }
  return updated;
};

const COMPANION_DEPENDENCY_TAG = "companion-dependency-order";

const normalizeTag = (value) =>
  null == value ? "" : String(value).trim().toLowerCase();

const hasCompanionDependencyTag = (finding) =>
  Array.isArray(finding?.tags) &&
  finding.tags.some((tag) => COMPANION_DEPENDENCY_TAG === normalizeTag(tag));

const applyCompanionDependencyRule = (finding, companionContext) => {
  if (true !== companionContext?.hasConfirmedCompanion) {
    return finding;
  }
  if (false === hasCompanionDependencyTag(finding)) {
    return finding;
  }
  const meta = resolveConventionalMeta(finding);
  if ("issue" !== meta.label) {
    return finding;
  }
  if (meta.decorations.includes("blocking")) {
    return {
      ...finding,
      comment_label: "issue",
      comment_decorations: ["non-blocking"],
      companionDependencyNonBlocking: true,
    };
  }
  if (meta.decorations.includes("non-blocking")) {
    return {
      ...finding,
      companionDependencyNonBlocking: true,
    };
  }
  return finding;
};

const enforceCaps = (findings, config, sizeKey) => {
  const caps = config?.comment_label_caps || {};
  const budget = config?.comment_budget_by_size?.[sizeKey] ?? Infinity;
  const grouped = {
    issue_blocking: [],
    issue_non_blocking: [],
    suggestion: [],
    question: [],
    note: [],
    nitpick: [],
    other: [],
  };
  findings.forEach((finding) => {
    const bucket = getFindingBucket(finding);
    const key = grouped[bucket] ? bucket : "other";
    grouped[key].push(finding);
  });
  const capped = [];
  const overflow = [];
  const capFor = (bucket) => caps[`${bucket}_max`] ?? Infinity;
  [
    "issue_blocking",
    "issue_non_blocking",
    "suggestion",
    "question",
    "note",
    "nitpick",
    "other",
  ].forEach((bucket) => {
    const list = grouped[bucket].sort(
      (a, b) => (b.confidence || 0) - (a.confidence || 0)
    );
    const keep = list.slice(0, capFor(bucket));
    const drop = list.slice(capFor(bucket));
    capped.push(...keep);
    overflow.push(...drop);
  });
  const sorted = capped.sort((a, b) => {
    const bucketDiff =
      (labelOrder[getFindingBucket(b)] || 0) -
      (labelOrder[getFindingBucket(a)] || 0);
    if (0 !== bucketDiff) {
      return bucketDiff;
    }
    return (b.confidence || 0) - (a.confidence || 0);
  });
  const budgeted = sorted.slice(0, budget);
  const budgetOverflow = sorted.slice(budget);
  return {
    budgeted,
    overflow: [...overflow, ...budgetOverflow],
  };
};

export const aggregateResults = task(
  { name: "aggregateResults" },
  async ({ facts, results }) => {
    log("aggregate: start");
    const thresholds  = facts.config?.confidence_thresholds;
    const validPaths  = new Set(facts.changedFiles || []);
    const allFindings = [];
    const reviewerStats = {};
    results.forEach((result) => {
      const parsed = result.parsed;
      if (null == parsed || false === Array.isArray(parsed.findings)) {
        return;
      }
      reviewerStats[result.reviewer] = parsed.findings.length;
      parsed.findings.forEach((finding) => {
        const filtered = filterFindingLocations(finding, validPaths);
        if (null === filtered) {
          return;
        }
        const confidenceAdjusted = applyConfidenceRules(filtered, thresholds);
        if (confidenceAdjusted) {
          const companionAdjusted = applyCompanionDependencyRule(
            confidenceAdjusted,
            facts.companionContext
          );
          allFindings.push({ ...companionAdjusted, reviewer: result.reviewer });
        }
      });
    });
    const retroFiltered = await applyRetroDupeFilter({
      facts,
      findings: allFindings,
    });
    const filteredFindings =
      Array.isArray(retroFiltered?.filtered) && 0 < retroFiltered.filtered.length
        ? retroFiltered.filtered
        : allFindings;
    const retroDroppedCount = Array.isArray(retroFiltered?.dropped)
      ? retroFiltered.dropped.length
      : 0;
    const retroReport = retroFiltered?.report ?? null;
    const { budgeted, overflow } = enforceCaps(
      filteredFindings,
      facts.config,
      facts.sizeKey
    );
    const prFindings = budgeted.filter((finding) => {
      const meta = resolveConventionalMeta(finding);
      if ("suggestion" === meta.label) {
        const confidenceMin =
          facts.config?.confidence_thresholds?.suggestion_min ??
          facts.config?.confidence_thresholds?.non_blocking_min ??
          0.75;
        return Number(finding?.confidence ?? 0) >= confidenceMin;
      }
      if ("issue" === meta.label && meta.decorations.includes("blocking")) {
        return true;
      }
      return (
        "issue" === meta.label &&
        true === finding?.companionDependencyNonBlocking
      );
    });
    const summaryForInline = { pr_comment: { findings: prFindings } };
    const inlineResult = facts.outputPaths
      ? await buildInlineComments(summaryForInline, facts)
      : { comments: [], inlinedKeys: new Set() };
    const inlinedKeys = inlineResult?.inlinedKeys || new Set();
    const prFindingsForComment = prFindings.map((finding) => ({
      ...finding,
      posted_inline: inlinedKeys.has(
        buildFindingKey(finding, finding.locations?.[0])
      ),
    }));
    const summaryCounts = (() => {
      const counts = new Map();
      prFindingsForComment.forEach((finding) => {
        const meta = resolveConventionalMeta(finding);
        const label = meta.label || "note";
        counts.set(label, (counts.get(label) || 0) + 1);
      });
      const orderedLabels = ["issue", "suggestion", "question", "note", "nitpick"];
      const summaryParts = orderedLabels
        .filter((label) => counts.has(label))
        .map((label) => `${counts.get(label)} ${label}${1 === counts.get(label) ? "" : "s"}`);
      const remaining = [...counts.entries()]
        .filter(([label]) => false === orderedLabels.includes(label))
        .map(([label, count]) => `${count} ${label}${1 === count ? "" : "s"}`);
      const allParts = [...summaryParts, ...remaining];
      return allParts.length ? allParts.join(", ") + "." : "No findings.";
    })();
    const summary = {
      pr_comment: {
        summary: summaryCounts,
        findings: prFindingsForComment,
      },
      private_summary: {
        summary: `Total findings: ${allFindings.length}.`,
        findings: [...budgeted, ...overflow],
        trends: [],
        reviewer_stats: reviewerStats,
      },
    };
    log(
      `aggregate: pr_findings=${prFindings.length} total_findings=${allFindings.length}`
    );
    if (facts.outputPaths) {
      writeJson(facts.outputPaths.aggregateFindings, summary);
      if (facts.outputPaths.retroDupeReport && retroReport) {
        writeJson(facts.outputPaths.retroDupeReport, retroReport);
      }
      const inlineComments = inlineResult?.comments || [];
      writeJson(
        path.join(facts.outputPaths.outputRoot, "aggregate/inline-comments.json"),
        inlineComments
      );
      const reviewersDecision = facts.outputPaths?.reviewersDecision
        ? readJson(facts.outputPaths.reviewersDecision)
        : null;
      const selectedReviewers = reviewersDecision?.selectedReviewers || [];
      const reviewerDefinitions = loadReviewerDefinitions(facts.repoRoot);
      const overallSummary = facts.outputPaths?.summariesOverall
        ? readJson(facts.outputPaths.summariesOverall)
        : null;
      const dynamicGroups = facts.outputPaths?.summariesDynamicGroups
        ? readJson(facts.outputPaths.summariesDynamicGroups)
        : null;
      const overallConfidence = Number(overallSummary?.confidence);
      const overallLines =
        overallSummary && false === overallSummary.skipped && overallSummary.summary
          ? [
              "## Overall Summary",
              overallSummary.summary,
              ...(Number.isFinite(overallConfidence)
                ? ["", `Confidence: ${Math.round(overallConfidence * 100)}%`]
                : []),
            ]
          : [];
      const reviewerLines = selectedReviewers.length
        ? (() => {
            const normalizedNames = selectedReviewers.map((name) =>
              name.replace(/^review-/, "")
            );
            const summaryLine = `(${normalizedNames.length}/${reviewerDefinitions.length}) ${normalizedNames.join(", ")}`;
            return [
              ...(reviewersDecision?.rationale ? [reviewersDecision.rationale, ""] : []),
              summaryLine,
            ];
          })()
        : [];
      const reviewerDetailsLines = reviewerLines.length
        ? [
            "<details>",
            "<summary>Reviewers</summary>",
            "",
            ...reviewerLines,
            "</details>",
          ]
        : [];
      const sizeLines = (() => {
        if (null == facts.sizeKey) {
          return [];
        }
        const sizeLabel = `${facts.sizeKey[0].toUpperCase()}${facts.sizeKey.slice(1)}`;
        const budget = facts.config?.comment_budget_by_size?.[facts.sizeKey];
        const reviewerRuns = facts.config?.reviewer_runs_by_size?.[facts.sizeKey];
        const parts = [`Size: ${sizeLabel}`];
        if (null != budget) {
          parts.push(`Comment Budget: ${budget}`);
        }
        if (null != reviewerRuns) {
          parts.push(`Reviewer Runs: ${reviewerRuns}`);
        }
        return parts.length ? [parts.join(", ") + "."] : [];
      })();
      const groupedChangesLines = (() => {
        if (
          null == dynamicGroups ||
          true === dynamicGroups.skipped ||
          false === Array.isArray(dynamicGroups.groups) ||
          0 === dynamicGroups.groups.length
        ) {
          return [];
        }
        const lines = ["## Grouped Changes"];
        dynamicGroups.groups.forEach((group, index) => {
          if (0 < index) {
            lines.push("");
          }
          const label = group.label || group.key || "Group";
          lines.push(`**${label}**`);
          lines.push(group.summary || "(no summary)");
          const filePaths = Array.isArray(group.file_paths) ? group.file_paths : [];
          filePaths.forEach((filePath) => {
            lines.push(`- \`${filePath}\``);
          });
        });
        return lines;
      })();
      const reviewFindings = prFindingsForComment.filter(
        (finding) => true !== finding.posted_inline
      );
      const exactDropped = retroReport?.prefilter?.dropped_count ?? 0;
      const modelDropped = retroReport?.model?.dropped_count ?? 0;
      const dropParts =
        retroDroppedCount > 0
          ? [
              exactDropped ? `${exactDropped} exact` : null,
              modelDropped ? `${modelDropped} model` : null,
            ].filter(Boolean)
          : [];
      const retroDropLine =
        retroDroppedCount > 0
          ? `Retro dupe filter: Dropped ${retroDroppedCount} duplicate finding${
              retroDroppedCount === 1 ? "" : "s"
            }${dropParts.length ? ` (${dropParts.join(", ")})` : ""}.`
          : null;
      const summaryCommentLines = [
        "<!-- dh:review-summary -->",
        "## DeepHive Summary",
        summary.pr_comment.summary,
        ...sizeLines,
        ...(retroDropLine ? [retroDropLine] : []),
        ...(0 < overallLines.length ? ["", ...overallLines] : []),
        ...(0 < groupedChangesLines.length ? ["", ...groupedChangesLines] : []),
        ...(0 < reviewerDetailsLines.length ? ["", ...reviewerDetailsLines] : []),
      ];
      const summaryCommentBody = `${summaryCommentLines.join("\n")}\n`;
      writeText(facts.outputPaths.aggregateSummaryComment, summaryCommentBody);
      const reviewCommentLines = [
        "## Summary",
        summary.pr_comment.summary,
        "",
        "## Findings",
        reviewFindings.length
          ? reviewFindings
              .map((finding, index) => {
                const header = buildConventionalHeaderFromFinding(finding);
                const titleLine = `${index + 1}. ${header}`;
                const detailLines = [];
                if (finding.reviewer) {
                  detailLines.push(
                    `   Reviewer: ${finding.reviewer.replace(/^review-/, "")}`
                  );
                }
                const confidenceValue = Number(finding.confidence);
                if (Number.isFinite(confidenceValue)) {
                  detailLines.push(`   Confidence: ${Math.round(confidenceValue * 100)}%`);
                }
                if (finding.rationale) {
                  detailLines.push(`   Rationale: ${finding.rationale}`);
                }
                if (finding.suggested_fix) {
                  detailLines.push(`   Suggestion: ${finding.suggested_fix}`);
                }
                const locations = Array.isArray(finding.locations)
                  ? finding.locations
                  : [];
                if (0 < locations.length) {
                  detailLines.push("   Locations:");
                  locations.forEach((location) => {
                    const pathLine = location?.path
                      ? `- \`${location.path}\`${location.lines ? ` (${location.lines})` : ""}`
                      : null;
                    if (pathLine) {
                      detailLines.push(`     ${pathLine}`);
                    }
                    if (location?.snippet) {
                      detailLines.push(`     ${location.snippet}`);
                    }
                  });
                }
                return [titleLine, ...detailLines].join("\n");
              })
              .join("\n")
          : prFindingsForComment.length
            ? "No additional feedback beyond inline comments."
            : "No findings.",
      ];
      const reviewCommentBody = `${reviewCommentLines.join("\n")}\n`;
      writeText(facts.outputPaths.aggregateReviewComment, reviewCommentBody);
      if (facts.outputPaths.aggregateReviewPayload) {
        const hasBlocking = prFindingsForComment.some((finding) => {
          const meta = resolveConventionalMeta(finding);
          return meta.decorations.includes("blocking");
        });
        const reviewEvent = hasBlocking
          ? "REQUEST_CHANGES"
          : prFindingsForComment.length
            ? "COMMENT"
            : "APPROVE";
        const inlineComments = inlineResult?.comments || [];
        writeJson(facts.outputPaths.aggregateReviewPayload, {
          event: reviewEvent,
          body: reviewCommentBody,
          comments: inlineComments,
        });
      }
      const privateLines = [
        "## Summary",
        summary.private_summary.summary,
        "",
        "## Findings",
        summary.private_summary.findings
          .map((finding, index) => {
            const meta = resolveConventionalMeta(finding);
            const decorationText = meta.decorations.length
              ? ` (${meta.decorations.join(", ")})`
              : "";
            const labelText = `${meta.label}${decorationText}`;
            return `${index + 1}. [${labelText}] ${finding.title || "Finding"}`;
          })
          .join("\n") || "No findings.",
        "",
        "## Reviewer Stats",
        JSON.stringify(summary.private_summary.reviewer_stats, null, 2),
      ];
      writeText(
        facts.outputPaths.aggregatePrivateSummary,
        `${privateLines.join("\n")}\n`
      );
    }
    const retroReviewer = results.find(
      (result) => "review-retro-feedback" === result?.reviewer
    );
    if (null != retroReviewer?.parsed) {
      applyRetroActions({ facts, retroResult: retroReviewer.parsed });
    }
    return summary;
  }
);
