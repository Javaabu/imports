{{--
    javaabu/imports guidelines for AI code assistants

    Maintained by: Javaabu (https://javaabu.com)
--}}

## javaabu/imports

- This project imports CSV, XLSX and ODS files with `javaabu/imports`. Write importers as classes extending `Javaabu\Imports\ModelImporter` with `Javaabu\Imports\Columns\Column` definitions, and run them with `preview()`, `import()` or `queue($file, $user)`; don't use maatwebsite/excel for them.
- Always activate the `javaabu-imports` skill when writing or changing an importer, adding an upload or import button, mapping or validating spreadsheet columns, importing a large file, showing import progress or failed rows, setting up the imports page, or changing who may import.
