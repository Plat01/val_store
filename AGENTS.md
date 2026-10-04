# собери станок

Правила и структура проекта: [CLAUDE.md](CLAUDE.md). Общение и тексты на русском.
Выполнять только запрошенные этапы плана. `old_site/` — источник, не изменять.

Навыки (копии установлены в `~/.codex/skills/`):
- `.claude/skills/wp-local/SKILL.md` — перед командами WordPress/Docker/импорта.
- `.claude/skills/brand-style/SKILL.md` — перед вёрсткой и дизайном.
- `.claude/skills/seo-check/SKILL.md` — перед изменениями URL/SEO/шаблонов.
- `frontend-design` — для интерфейса, с приоритетом правил бренда.

Все настройки воспроизводить через `site/scripts/setup*.sh`, не только через админку.
Проверки: `npm test`, `npm run check:mcp`, `site/scripts/verify.sh`.
Подробности запуска и проверки этапов 0–3: [site/README.md](site/README.md).
