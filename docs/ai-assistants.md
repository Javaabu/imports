---
title: AI assistants
sidebar_position: 1.35
---

The docs are plain Markdown, written for people and AI coding assistants alike. The package ships them, so they always match the version you have installed.

| File | What |
| --- | --- |
| `vendor/javaabu/imports/llms.txt` | The rules for using the package, and every page |
| `vendor/javaabu/imports/docs/` | The pages |
| `vendor/javaabu/imports/resources/boost/` | The Laravel Boost skill and guideline |

# Laravel Boost

[Laravel Boost](https://github.com/laravel/boost) finds the skill and guideline by itself in Composer packages. Run this after installing or updating the package:

```bash
php artisan boost:update
```

Choose `javaabu/imports` if Boost asks which third-party packages to use.

# Other assistants

Point your assistant at the docs from the project's instructions file (`AGENTS.md`, `CLAUDE.md`, `.cursor/rules`, `.github/copilot-instructions.md`):

```md
## Imports

Spreadsheet imports use javaabu/imports. Before writing an importer, read vendor/javaabu/imports/llms.txt.
```

Or copy `vendor/javaabu/imports/resources/boost/skills/javaabu-imports` into your assistant's skills folder, such as `.claude/skills/`.
