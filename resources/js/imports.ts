/**
 * Live progress for the Blade imports pages.
 *
 * Counts marked with data-import-count update too, and the rows text uses the
 * container's data-rows-template (":processed of :total rows", translated).
 *
 * Rows marked with data-import-row update themselves: over Laravel Echo when the page
 * says broadcasting is on and window.Echo exists, by polling the JSON show route otherwise.
 * When an import finishes, the page reloads to show its actions. Forms with data-confirm
 * ask before they submit.
 */
import type { ImportProgressPayload } from "./types";

interface EchoLike {
  private(channel: string): {
    listen(event: string, callback: (payload: ImportProgressPayload) => void): unknown;
  };
}

declare global {
  interface Window {
    Echo?: EchoLike;
  }
}

const EVENTS = [".import.started", ".import.progressed", ".import.completed", ".import.failed", ".import.cancelled"];

const COUNTS = ["created_rows", "updated_rows", "skipped_rows", "failed_rows"] as const;

/** ":processed of :total rows" with the numbers filled in */
export function rowsText(template: string, processed: number, total: number | null): string {
  if (total === null) {
    return "";
  }

  return template.replace(":processed", processed.toLocaleString()).replace(":total", total.toLocaleString());
}

export function updateRow(row: HTMLElement, data: ImportProgressPayload, template = ":processed / :total"): void {
  const bar = row.querySelector<HTMLElement>("[data-import-progress-bar]");
  const text = row.querySelector<HTMLElement>("[data-import-progress-text]");
  const rows = row.querySelector<HTMLElement>("[data-import-rows]");
  const status = row.querySelector<HTMLElement>("[data-import-status] [data-slot='status-badge']");
  const meter = row.querySelector<HTMLElement>("[role='progressbar']");

  if (bar && data.progress !== null) {
    bar.style.inlineSize = `${data.progress}%`;
    bar.classList.remove("animate-pulse");
  }

  if (text) {
    text.textContent = data.progress === null ? "" : `${data.progress}%`;
  }

  if (meter && data.progress !== null) {
    meter.setAttribute("aria-valuenow", String(data.progress));
  }

  if (rows && data.total_rows !== null) {
    rows.textContent = rowsText(template, data.processed_rows, data.total_rows);
  }

  for (const count of COUNTS) {
    const element = row.querySelector<HTMLElement>(`[data-import-count='${count}']`);

    if (element) {
      element.textContent = data[count].toLocaleString();
    }
  }

  if (status) {
    status.textContent = data.status_label;
  }

  if (data.is_finished) {
    row.dataset.importFinished = "true";
  }
}

function follow(container: HTMLElement): void {
  const rows = Array.from(container.querySelectorAll<HTMLElement>("[data-import-row]")).filter(
    (row) => row.dataset.importFinished !== "true",
  );

  if (rows.length === 0) {
    return;
  }

  const onFinished = () => window.location.reload();
  const template = container.dataset.rowsTemplate || ":processed / :total";
  const broadcasting = container.dataset.broadcasting === "true" && window.Echo !== undefined;

  if (broadcasting) {
    for (const row of rows) {
      const channel = row.dataset.importChannel;

      if (!channel) {
        continue;
      }

      const subscription = window.Echo!.private(channel);

      for (const event of EVENTS) {
        subscription.listen(event, (payload) => {
          updateRow(row, payload, template);

          if (payload.is_finished) {
            onFinished();
          }
        });
      }
    }

    return;
  }

  const interval = Number(container.dataset.pollInterval) || 3000;

  const poll = async () => {
    const pending = rows.filter((row) => row.dataset.importFinished !== "true");

    if (pending.length === 0) {
      return;
    }

    let finished = false;

    await Promise.all(
      pending.map(async (row) => {
        const url = row.dataset.importUrl;

        if (!url) {
          return;
        }

        const response = await fetch(url, {
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
          credentials: "same-origin",
        });

        if (!response.ok) {
          return;
        }

        const { data } = (await response.json()) as { data: ImportProgressPayload };

        updateRow(row, data, template);
        finished ||= data.is_finished;
      }),
    );

    if (finished) {
      onFinished();

      return;
    }

    window.setTimeout(poll, interval);
  };

  window.setTimeout(poll, interval);
}

function confirmForms(): void {
  document.addEventListener("submit", (event) => {
    const form = event.target as HTMLFormElement | null;
    const message = form?.dataset?.confirm;

    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });
}

export function init(): void {
  document.querySelectorAll<HTMLElement>("[data-imports]").forEach(follow);
  confirmForms();
}

if (typeof document !== "undefined") {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
}
