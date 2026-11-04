import { Form, LinkButton, Submit } from "@javaabu/forms";
import type { MouseEvent } from "react";
import type { ImportData, ImportsStrings } from "../types";

export interface ImportActionsProps {
  import: ImportData;
  strings: Pick<ImportsStrings, "view" | "map_columns" | "download_report" | "retry" | "cancel" | "delete" | "confirm_cancel" | "confirm_delete">;
  /** In a list: also link to the import, and leave out Try again */
  compact?: boolean;
}

function confirmFirst(message: string) {
  return (event: MouseEvent<HTMLButtonElement>) => {
    if (!window.confirm(message)) {
      event.preventDefault();
    }
  };
}

/** View, download the failed rows, try again, cancel and delete, when the user may */
export function ImportActions({ import: item, strings, compact = false }: ImportActionsProps) {
  const retryable = item.status === "failed" || item.status === "cancelled";

  return (
    <div className="flex flex-wrap items-center gap-2" data-import-actions>
      {compact && item.links.show ? (
        <LinkButton url={item.links.show} color="secondary" size="sm">
          {item.is_draft && item.can.update ? strings.map_columns : strings.view}
        </LinkButton>
      ) : null}

      {item.has_report && item.can.download && item.links.report ? (
        <LinkButton url={item.links.report} download size="sm" startIcon="download">
          {strings.download_report}
        </LinkButton>
      ) : null}

      {!compact && retryable && item.can.update && item.links.run ? (
        <Form method="POST" action={item.links.run}>
          <Submit color="secondary" size="sm" animate={false}>
            {strings.retry}
          </Submit>
        </Form>
      ) : null}

      {item.is_cancellable && item.can.cancel && item.links.cancel ? (
        <Form method="POST" action={item.links.cancel}>
          <Submit color="secondary" size="sm" animate={false} onClick={confirmFirst(strings.confirm_cancel)}>
            {strings.cancel}
          </Submit>
        </Form>
      ) : null}

      {item.can.delete && item.links.destroy ? (
        <Form method="DELETE" action={item.links.destroy}>
          <Submit color="danger" size="sm" animate={false} onClick={confirmFirst(strings.confirm_delete)}>
            {strings.delete}
          </Submit>
        </Form>
      ) : null}
    </div>
  );
}
