import { minimatch } from "minimatch";

export const classifySize = (lineCount, config) => {
  if (null == config?.review_size) {
    return "medium";
  }
  const {
    tiny_max_lines: tinyMax,
    small_max_lines: smallMax,
    medium_max_lines: mediumMax,
    large_max_lines: largeMax,
  } = config.review_size;
  if (lineCount <= tinyMax) {
    return "tiny";
  }
  if (lineCount <= smallMax) {
    return "small";
  }
  if (lineCount <= mediumMax) {
    return "medium";
  }
  if (lineCount <= largeMax) {
    return "large";
  }
  return "huge";
};

export const rankFilesBySize = (files) =>
  [...files].sort((a, b) => {
    const sizeA = (a.additions ?? 0) + (a.deletions ?? 0);
    const sizeB = (b.additions ?? 0) + (b.deletions ?? 0);
    return sizeB - sizeA;
  });

export const hasPathMatch = (filePath, patterns) =>
  patterns.some((pattern) => minimatch(filePath, pattern, { dot: true }));

export const buildRequiredReviewers = (facts) => {
  const reviewers = new Set();
  const sizeKey = facts.sizeKey || "medium";
  const codeFiles = Array.isArray(facts.codeFiles) ? facts.codeFiles : [];
  const lowerTitle = String(facts.prMeta?.title || "").toLowerCase();
  const lowerHead = String(facts.headRef || "").toLowerCase();

  const baseSet =
    "large" === sizeKey || "huge" === sizeKey
      ? [
          "review-change-intent",
          "review-correctness",
          "review-security",
          "review-api-contract",
          "review-error-handling",
          "review-performance",
        ]
      : [
          "review-change-intent",
          "review-code-clarity",
          "review-correctness",
          "review-divi-architecture",
          "review-performance",
          "review-security",
        ];
  baseSet.forEach((name) => reviewers.add(name));

  const hasJsTs = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["**/*.js", "**/*.jsx", "**/*.ts", "**/*.tsx"])
  );
  const hasPhp = codeFiles.some((filePath) => hasPathMatch(filePath, ["**/*.php"]));
  const hasUiTemplates = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["**/*.tsx", "**/*.jsx", "**/*.php", "**/*.html"])
  );
  const hasI18n = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["**/i18n/**", "**/*.po", "**/*.mo"])
  );
  const hasApiContracts = codeFiles.some((filePath) =>
    hasPathMatch(filePath, [
      "**/api/**",
      "**/rest/**",
      "**/graphql/**",
      "**/contracts/**",
      "**/types/**",
      "**/schema/**",
    ])
  );
  const hasAuthSecurity = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["**/auth/**", "**/security/**", "**/permissions/**"])
  );
  const hasMigrations = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["**/migrations/**", "**/database/**", "**/schema/**"])
  );
  const hasTests = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["**/__tests__/**", "**/*.spec.*", "**/*.test.*"])
  );
  const hasDeps = codeFiles.some((filePath) =>
    hasPathMatch(filePath, [
      "**/package.json",
      "**/yarn.lock",
      "**/package-lock.json",
      "**/pnpm-lock.yaml",
      "**/composer.json",
      "**/composer.lock",
    ])
  );
  const hasDiviArchitectureSignals = codeFiles.some((filePath) =>
    /(conversion-outline|module\.json-source|\/conversion\/|\/module-library\/|global-data|GlobalData|\bd4\b|\bd5\b)/i.test(
      filePath
    )
  );
  const hasAttrIntegritySignals = codeFiles.some((filePath) =>
    /(attrs|attrs-map|attrsmap|attr-map|attrmap|group-preset|grouppreset|renderattrs|styleattrs|dynamicoptiongroups|clipboard|right-click-options|modal-library|update-attribute|parse-serialized|serialize|module-utils|module-library)/i.test(
      filePath
    )
  );
  const hasSpecs = codeFiles.some((filePath) =>
    hasPathMatch(filePath, ["includes/builder-5/specs/**"])
  );
  const hasSpecMap = codeFiles.some((filePath) =>
    hasPathMatch(filePath, [
      "includes/builder-5/specs/**/spec-map.*",
      "includes/builder-5/specs/spec-map.*",
    ])
  );
  const hasBugfixSignal =
    /(fix|bug|hotfix|patch|regression)/i.test(lowerTitle) ||
    /(fix|bug|hotfix|patch|regression)/i.test(lowerHead);
  const hasRetroFeedback =
    true === facts?.retroReview?.enabled &&
    0 < Number(facts?.retroReview?.thread_count || 0);

  if (hasJsTs) {
    reviewers.add("review-type-quality");
    reviewers.add("review-types-structure");
    reviewers.add("review-performance");
    reviewers.add("review-ux-accessibility");
  }
  if (hasPhp) {
    reviewers.add("review-error-handling");
    reviewers.add("review-security");
    reviewers.add("review-performance");
  }
  if (hasUiTemplates) {
    reviewers.add("review-ux-accessibility");
    reviewers.add("review-i18n");
  }
  if (hasI18n) {
    reviewers.add("review-i18n");
  }
  if (hasApiContracts) {
    reviewers.add("review-api-contract");
  }
  if (hasAuthSecurity) {
    reviewers.add("review-security");
  }
  if (hasMigrations) {
    reviewers.add("review-rollout-migration");
    reviewers.add("review-api-contract");
    reviewers.add("review-data-persistence");
  }
  if (hasAttrIntegritySignals) {
    reviewers.add("review-data-persistence");
    reviewers.add("review-bugfix-validation");
    reviewers.add("review-divi-architecture");
  }
  if (hasTests) {
    reviewers.add("review-test-quality");
  }
  if (hasDeps) {
    reviewers.add("review-dependencies");
  }
  if (hasDiviArchitectureSignals) {
    reviewers.add("review-divi-architecture");
  }
  if (hasSpecs) {
    reviewers.add("review-spec-alignment");
  }
  if (hasSpecMap) {
    reviewers.add("review-spec-map");
  }
  if (hasBugfixSignal) {
    reviewers.add("review-bugfix-validation");
  }
  if (hasRetroFeedback) {
    reviewers.add("review-retro-feedback");
  }

  return reviewers;
};

export const selectReviewerFiles = ({ reviewer, summaries, maxFiles = 12 }) => {
  const files = summaries?.files || [];
  if (0 === files.length) {
    return [];
  }
  const lowerKeywords = (reviewer.keywords || []).map((keyword) =>
    keyword.toLowerCase()
  );
  const matched = files.filter((file) => {
    const pathMatch = (reviewer.globs || []).some((glob) =>
      minimatch(file.path, glob, { dot: true })
    );
    if (pathMatch) {
      return true;
    }
    if (0 === lowerKeywords.length) {
      return false;
    }
    const haystack = `${file.path} ${file.summary || ""}`.toLowerCase();
    return lowerKeywords.some((keyword) => haystack.includes(keyword));
  });
  const ranked = rankFilesBySize(matched);
  if (0 < ranked.length) {
    return ranked.slice(0, maxFiles);
  }
  return rankFilesBySize(files).slice(0, Math.min(maxFiles, 5));
};

export const resolveReviewerRuns = ({ reviewer, sizeKey, config }) => {
  const configRuns = config?.reviewer_runs_by_size?.[sizeKey];
  const reviewerRuns = reviewer?.runsBySize?.[sizeKey];
  let resolved = Number.isFinite(reviewerRuns)
    ? reviewerRuns
    : Number.isFinite(configRuns)
      ? configRuns
      : 1;
  if (Number.isFinite(reviewer?.maxRuns)) {
    resolved = Math.min(resolved, reviewer.maxRuns);
  }
  if (!Number.isFinite(resolved) || resolved < 1) {
    return 1;
  }
  return Math.max(1, Math.floor(resolved));
};

export const resolveReviewerModels = ({ reviewer, reviewerModel, config, runCount }) => {
  const preferred =
    Array.isArray(reviewer?.models) && reviewer.models.length
      ? reviewer.models
      : config?.reviewer_models;
  const baseModels =
    Array.isArray(preferred) && preferred.length
      ? preferred
      : null != reviewerModel
        ? [reviewerModel]
        : [];
  if (0 === baseModels.length) {
    return Array.from({ length: runCount }, () => null);
  }
  return Array.from({ length: runCount }, (_, index) =>
    baseModels[index % baseModels.length]
  );
};
