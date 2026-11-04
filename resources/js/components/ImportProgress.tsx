import { ProgressBar } from "@javaabu/onu";
import type { ImportData, ImportProgressPayload, ImportsStrings } from "../types";

export interface ImportProgressProps {
  import: Pick<ImportProgressPayload, "progress" | "status" | "is_finished" | "processed_rows" | "total_rows">;
  strings: Pick<ImportsStrings, "progress" | "rows_of">;
}

const COLORS: Record<ImportData["status"], "primary" | "success" | "destructive" | "muted" | "info"> = {
  draft: "muted",
  pending: "muted",
  processing: "info",
  completed: "success",
  failed: "destructive",
  cancelled: "muted",
};

/** ":processed of :total rows" with the numbers filled in; empty while the total is unknown */
export function rowsText(template: string, processed: number, total: number | null): string {
  if (total === null) {
    return "";
  }

  return template.replace(":processed", processed.toLocaleString()).replace(":total", total.toLocaleString());
}

/** A progress bar for an import; indeterminate while the rows are being counted */
export function ImportProgress({ import: item, strings }: ImportProgressProps) {
  const running = item.status === "pending" || item.status === "processing";
  const value = item.progress ?? (running ? null : 0);

  return (
    <div className="flex min-w-32 flex-col gap-1" data-import-progress>
      <ProgressBar value={value} label={strings.progress} showValue={item.progress !== null} size="sm" color={COLORS[item.status]} />
      <span className="text-muted-foreground text-xs tabular-nums">{rowsText(strings.rows_of, item.processed_rows, item.total_rows)}</span>
    </div>
  );
}
