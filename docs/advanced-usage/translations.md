---
title: Dhivehi and translations
sidebar_position: 5
---

Every string the package shows goes through `__()` with its English text as the key, and has a Dhivehi translation in `lang/dv.json`. Pages, messages, notifications, column labels, templates and failure reports follow the app's locale; queued imports use the locale they were queued in.

# Your columns

Labels go through `__()`, so add your columns' Dhivehi to your app's `lang/dv.json`:

```json
{
    "SKU": "އެސްކޭޔޫ",
    "Brand": "ބްރޭންޑް",
    "Released On": "ރިލީޒްކުރި ތާރީޚް"
}
```

Headings are matched against the label in the current locale and in English, so a file with Dhivehi headings maps automatically in a Dhivehi app, and an English file still does.

# Reading values

- Yes/No also accept the translated words (`އާނ`, `ނޫން`).
- Enums accept their `label()` in the current locale.
- Text is kept as it is, including Thaana. CSV files are read as UTF-8 (`imports.csv.encoding`), with or without a byte order mark.

# Right to left

The pages use logical utilities only (`ms-`, `pe-`, `text-start`), and file names and values are shown with `dir="auto"`. XLSX templates and failure reports are right-to-left when the locale is in `imports.rtl_locales`.

# Overriding

Publish nothing: add the same keys to your app's `lang/en.json` or `lang/dv.json` and yours win.

# For review

These translations are the agent's best effort and need a Dhivehi speaker to check them:

| English | Dhivehi |
| --- | --- |
| Dry run / This was a dry run: nothing was saved. | ޓެސްޓް ރަން / މިއީ ޓެސްޓް ރަނެއް: އެއްވެސް އެއްޗެއް ސޭވެއް ނުކުރޭ. |
| Map columns | ކޮލަމްތައް ގުޅާ |
| :field is chosen for more than one column. | :field ހޮވާފައިވަނީ އެއް ކޮލަމަށް ވުރެ ގިނައިން. |
| Fail rows that already exist | ކުރިން ހުރި ރެކޯޑްތަކާ ދިމާވާ ރޯތައް ނާކާމިޔާބުކުރޭ |
| Your :name import finished with :count failed rows | ތިބާގެ :name އިމްޕޯޓް ނިމުނީ :count ރޯ ނާކާމިޔާބުވެގެން |
| Excel (XLSX), CSV or OpenDocument (ODS), up to :size. | އެކްސެލް (XLSX)، CSV ނުވަތަ އޯޕަން ޑޮކިއުމަންޓް (ODS)، :size އަށް ވުރެ ބޮޑުނޫން. |
| Skip / Skipped | ދޫކޮށްލާ / ދޫކޮށްލެވުނު |
| Sample values | ނަމޫނާ އަގުތައް |

Status names, "Cancel", "Delete" and similar match javaabu/exports.
