import { Alert, Card, Status } from "@javaabu/forms";
import type { ImportsStrings, PreviewData } from "../types";

export interface PreviewTableProps {
  preview: PreviewData;
  strings: Pick<ImportsStrings, "preview" | "preview_help" | "row" | "result" | "errors" | "no_errors" | "created_rows" | "updated_rows" | "skipped_rows">;
}

const OUTCOME_COLORS = { created: "success", updated: "info", skipped: "secondary" } as const;

/** The first rows checked with the current mapping: their values, errors and what would happen */
export function PreviewTable({ preview, strings }: PreviewTableProps) {
  const mapped = preview.columns.filter((column) => column.heading !== null);
  const counts: Array<[string, number, boolean]> = [
    [strings.created_rows, preview.counts.created, false],
    [strings.updated_rows, preview.counts.updated, false],
    [strings.skipped_rows, preview.counts.skipped, false],
    [strings.errors, preview.counts.invalid, true],
  ];

  return (
    <Card title={strings.preview} data-import-preview>
      <p className="text-muted-foreground text-sm">{strings.preview_help.replace(":count", String(preview.counts.checked))}</p>

      <dl className="mt-4 flex flex-wrap gap-6 text-sm">
        {counts.map(([label, value, danger]) => (
          <div key={label}>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className={`text-lg font-semibold tabular-nums${danger ? " text-destructive" : ""}`}>{value.toLocaleString()}</dd>
          </div>
        ))}
      </dl>

      {preview.counts.invalid === 0 ? (
        <Alert type="success" className="mt-4">
          {strings.no_errors}
        </Alert>
      ) : null}

      <div className="mt-4 overflow-x-auto">
        <table className="w-full text-sm" data-slot="table">
          <thead>
            <tr className="border-b">
              <th className="p-2 text-start font-medium">{strings.row}</th>
              {mapped.map((column) => (
                <th key={column.attribute} className="p-2 text-start font-medium">
                  {column.label}
                </th>
              ))}
              <th className="p-2 text-start font-medium">{strings.result}</th>
            </tr>
          </thead>
          <tbody>
            {preview.rows.map((row) => {
              const messages = Object.values(row.errors).flat();

              return (
                <tr key={row.row} className={`border-b align-top${messages.length ? " bg-destructive/5" : ""}`} data-import-preview-row={row.row}>
                  <td className="text-muted-foreground p-2 tabular-nums">{row.row}</td>
                  {mapped.map((column) => (
                    <td key={column.attribute} className={`p-2${row.errors[column.attribute] ? " text-destructive" : ""}`} dir="auto">
                      {row.values[column.attribute] ?? ""}
                    </td>
                  ))}
                  <td className="p-2">
                    {messages.length ? (
                      <ul className="text-destructive list-disc ps-4">
                        {messages.map((message, index) => (
                          <li key={index}>{message}</li>
                        ))}
                      </ul>
                    ) : row.outcome ? (
                      <Status color={OUTCOME_COLORS[row.outcome]} label={row.outcome_label ?? row.outcome} />
                    ) : null}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </Card>
  );
}
