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
