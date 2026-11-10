# Backlog

Product artifacts that live **upstream** of OpenSpec. Flow:

```
discovery/  →  tasks/  →  openspec/changes/  →  code
(/req-capture) (/task-generate, /task-enrich)  (/opsx-propose)
```

| Dir | Contents |
|-----|----------|
| `discovery/` | Requirements-gathering notes, one file per topic (`/req-capture`) |
| `tasks/` | Tasks `T-<nn>-<slug>.md` with frontmatter traceability (`/task-generate`) |

Task IDs are local (`T-01`, `T-02`, ...). A task's frontmatter (`status`, `change`)
is the single place that tracks where it is in the pipeline. Commands update it;
don't edit it by hand unless correcting drift.

## No sensitive information

This repository is **public**. Discovery docs and tasks must never contain:

- Secrets: API keys, tokens, passwords, Client IDs/secrets, private SSH keys.
- Server reconnaissance: hosting usernames, absolute server paths, IPs, or
  which PHP functions / security controls are enabled or disabled.
- Personal data beyond what is already public.
- Written admissions of breaching third-party terms of service — describe
  accepted risks neutrally ("unofficial library; may stop working").

Use placeholders (`<usuario>`, `~/domains/...`, `DEPLOY_PATH`) and keep real
values in GitHub secrets/variables or the server's `.env`. When data read from
an account informs a decision, record the decision, not the raw data.
See the rule in `AGENTS.md`.
