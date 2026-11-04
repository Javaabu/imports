import type { ImportProgressPayload, ImportsStrings } from "../types";

export interface ImportCountsProps {
  import: Pick<ImportProgressPayload, "created_rows" | "updated_rows" | "skipped_rows" | "failed_rows">;
  strings: Pick<ImportsStrings, "created_rows" | "updated_rows" | "skipped_rows" | "failed_rows">;
}

const COUNTS = ["created_rows", "updated_rows", "skipped_rows", "failed_rows"] as const;

/** New, updated, skipped and failed rows */
export function ImportCounts({ import: item, strings }: ImportCountsProps) {
  return (
    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-4" data-import-counts>
      {COUNTS.map((count) => (
        <div key={count}>
          <dt className="text-muted-foreground text-sm">{strings[count]}</dt>
          <dd
            className={`text-2xl font-semibold tabular-nums${count === "failed_rows" && item.failed_rows > 0 ? " text-destructive" : ""}`}
            data-import-count={count}
          >
            {item[count].toLocaleString()}
          </dd>
        </div>
      ))}
    </dl>
  );
}
