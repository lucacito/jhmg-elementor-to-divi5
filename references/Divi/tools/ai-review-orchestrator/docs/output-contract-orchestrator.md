---
title: AI Reviewer Output Contract (Orchestrator)
status: draft
---

# AI Reviewer Output Contract (Orchestrator)

This document standardizes the payloads produced by the orchestrator after
aggregating reviewer findings.

## Orchestrator Output

The orchestrator aggregates and enforces budgets to produce two outputs:

```
{
  "pr_comment": {
    "summary": "2 issues, 1 suggestion.",
    "findings": [ ... ],
    "notes": "Optional reviewer-facing note."
  },
  "private_summary": {
    "summary": "Full set of findings and trends.",
    "findings": [ ... ],
    "trends": [ ... ],
    "reviewer_stats": { "security-reviewer": 1 }
  }
}
```

Notes:
- The CI workflow posts only **issue (blocking)** findings to PR comments by default. High-confidence suggestions may also be posted, but non-blocking feedback is otherwise kept out of PR comments.
- Summary-only mode outputs a single line in `pr_comment.summary`.
- PR comment markdown renders findings using Conventional Comments format.
- `pr_comment.summary` counts Conventional Comment labels, not severities.
- Default to blocking "issues"; "suggestions" should be rare and high-confidence.

## Inline Comments Output

When running in `pr-compare` mode, the orchestrator emits an inline comments
payload for GitHub review APIs:

```
[
  {
    "path": "visual-builder/packages/module/src/component.tsx",
    "line": 120,
    "side": "RIGHT",
    "body": "**issue (blocking):** Missing fallback for empty attrs."
  },
  {
    "path": "visual-builder/packages/module/src/component.tsx",
    "line": 120,
    "side": "RIGHT",
    "body": "**suggestion (non-blocking):** Add a fallback for empty attrs."
  },
]
```

Notes:
- Prefer `line` + `side` for robust placement; `position` is a legacy fallback.
- Inline comments are only emitted when the finding maps to a diff hunk.
