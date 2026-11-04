import { Alert, Card, Checkbox, Form, Hidden, Select, Submit } from "@javaabu/forms";
import type { ImportData, ImportsStrings, Option, PreviewData } from "../types";

export interface ColumnMappingProps {
  import: ImportData;
  preview: PreviewData;
  duplicateOptions: Option<ImportData["duplicates"]>[];
  strings: Pick<
    ImportsStrings,
    | "map_columns"
    | "map_columns_help"
    | "column_in_file"
    | "sample_values"
    | "field"
    | "dont_import"
    | "missing_required"
    | "existing_records"
    | "dry_run"
    | "preview"
    | "run"
  >;
}

/**
 * The mapping step: each heading of the file with sample values and the field it fills,
 * how to treat existing records, and Preview / Start import. Preview posts `intent=preview`
 * to the run route, which saves the mapping and shows the new preview.
 */
export function ColumnMapping({ import: item, preview, duplicateOptions, strings }: ColumnMappingProps) {
  const fields = Object.fromEntries(
    preview.columns.map((column) => [column.attribute, column.required ? `${column.label} *` : column.label]),
  );
  const missing = preview.columns.filter((column) => preview.missing.includes(column.attribute)).map((column) => column.label);
  const editable = item.can.update;

  return (
    <Form method="POST" action={item.links.run ?? ""} className="flex flex-col gap-6" data-import-mapping disabled={!editable}>
      <Card title={strings.map_columns}>
        <p className="text-muted-foreground text-sm">{strings.map_columns_help}</p>

        {missing.length > 0 ? (
          <Alert type="warning" className="mt-4">
            {strings.missing_required} {missing.join(", ")}
          </Alert>
        ) : null}

        <div className="mt-4 overflow-x-auto">
          <table className="w-full text-sm" data-slot="table">
            <thead>
              <tr className="border-b">
                <th className="p-2 text-start font-medium">{strings.column_in_file}</th>
                <th className="p-2 text-start font-medium">{strings.sample_values}</th>
                <th className="p-2 text-start font-medium">{strings.field}</th>
              </tr>
            </thead>
            <tbody>
              {preview.headings.map((heading) => (
                <tr key={heading.index} className="border-b align-top" data-import-heading={heading.index}>
                  <td className="p-2 font-medium" dir="auto">
                    {heading.heading !== "" ? heading.heading : "—"}
                  </td>
                  <td className="text-muted-foreground p-2">
                    {heading.samples.map((sample, index) => (
                      <div key={index} className="max-w-64 truncate" dir="auto">
                        {sample}
                      </div>
                    ))}
                  </td>
                  <td className="p-2">
                    <Select
                      name={`mapping[${heading.index}]`}
                      label={strings.field}
                      showLabel={false}
                      placeholder={strings.dont_import}
                      showPlaceholder
                      options={fields}
                      default={heading.attribute ?? ""}
                    />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Card
        footer={
          editable ? (
            <div className="flex flex-wrap items-center gap-2">
              <Submit color="secondary" animate={false} name="intent" value="preview" data-import-preview-button>
                {strings.preview}
              </Submit>
              <Submit disabled={!preview.ready}>{strings.run}</Submit>
            </div>
          ) : null
        }
      >
        <div className="flex flex-wrap items-end gap-6">
          <Select
            name="duplicates"
            label={strings.existing_records}
            options={Object.fromEntries(duplicateOptions.map((option) => [option.value, option.label]))}
            default={item.duplicates}
          />
          <Hidden name="dry_run" value="0" />
          <Checkbox name="dry_run" value="1" label={strings.dry_run} default={item.dry_run} />
        </div>
      </Card>
    </Form>
  );
}
