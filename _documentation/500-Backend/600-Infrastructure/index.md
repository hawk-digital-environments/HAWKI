# Infrastructure

Cross-cutting backend infrastructure that is neither a [Concept](../200-Concepts/index.md) nor a [Domain](../400-Domains/index.md): runtime services operators and contributors encounter outside any single domain.

| Page | Covers |
|---|---|
| [Health Checks](./100-Health-Checks.md) | `HealthChecker`, `HealthTimer`, the `/health` endpoint. |
| [Scheduling](./200-Scheduling.md) | `ScheduleWithDynamicInterval` — DB-driven command frequencies. |
| [SSRF Protection](./300-SSRF-Protection.md) | `Http::getSsrfSafe()` — the canonical home for the SSRF-safe HTTP macro. |
