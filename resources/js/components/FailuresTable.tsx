import { Card } from "@javaabu/forms";
import type { ImportsStrings, RowFailureData } from "../types";

export interface FailuresTableProps {
  failures: RowFailureData[];
  strings: Pick<ImportsStrings, "failed_rows" | "failed_rows_help" | "row" | "errors">;
}

/** The failed rows kept on the import (the first imports.failure_samples) */
export function FailuresTable({ failures, strings }: FailuresTableProps) {
  if (failures.length === 0) {
    return null;
  }

  return (
    <Card title={strings.failed_rows} data-import-failures>
      <p className="text-muted-foreground text-sm">{strings.failed_rows_help}</p>

      <div className="mt-4 overflow-x-auto">
        <table className="w-full text-sm" data-slot="table">
          <thead>
            <tr className="border-b">
              <th className="p-2 text-start font-medium">{strings.row}</th>
              <th className="p-2 text-start font-medium">{strings.errors}</th>
            </tr>
          </thead>
          <tbody>
            {failures.map((failure) => (
              <tr key={failure.row} className="border-b align-top">
                <td className="text-muted-foreground p-2 tabular-nums">{failure.row}</td>
                <td className="p-2">
                  <ul className="list-disc ps-4">
                    {failure.messages.map((message, index) => (
                      <li key={index}>{message}</li>
                    ))}
                  </ul>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}
