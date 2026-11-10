import { useEffect, useRef, useState } from "react";
import type { ImportData, ImportProgressPayload } from "../types";

interface EchoLike {
  private(channel: string): {
    listen(event: string, callback: (payload: ImportProgressPayload) => void): unknown;
  };
  leave(channel: string): void;
}

export interface UseImportProgressOptions {
  /** Listen with window.Echo when true and Echo is there; poll otherwise */
  broadcasting: boolean;
  /** Milliseconds between polls */
  pollInterval: number;
  /** Called when an import finishes */
  onFinished?: (importData: ImportData) => void;
}

const EVENTS = [".import.started", ".import.progressed", ".import.completed", ".import.failed", ".import.cancelled"];

/** Queued or processing: drafts wait for their mapping and finished imports don't change */
export function isRunning(item: Pick<ImportData, "is_finished" | "is_draft">): boolean {
  return !item.is_finished && !item.is_draft;
}

function echo(): EchoLike | undefined {
  return typeof window === "undefined" ? undefined : (window as unknown as { Echo?: EchoLike }).Echo;
}

/**
 * Keeps a list of imports up to date while they run: over Laravel Echo when the page
 * broadcasts, by polling each unfinished import's show route otherwise.
 */
export function useImportProgress(initial: ImportData[], options: UseImportProgressOptions): ImportData[] {
  const [exports, setImports] = useState(initial);
  const options_ref = useRef(options);
  options_ref.current = options;

  useEffect(() => setImports(initial), [initial]);

  const unfinished = exports.filter((item) => isRunning(item)).map((item) => item.id).join(",");

  useEffect(() => {
    if (unfinished === "") {
      return;
    }

    const merge = (payload: ImportProgressPayload) => {
      setImports((current) =>
        current.map((item) => {
          if (item.id !== payload.id) {
            return item;
          }

          const updated = { ...item, ...payload };

          if (payload.is_finished && isRunning(item)) {
            options_ref.current.onFinished?.(updated);
          }

          return updated;
        }),
      );
    };

    const pending = exports.filter((item) => isRunning(item));
    const client = echo();

    if (options.broadcasting && client) {
      const channels = pending.map((item) => item.broadcast_channel).filter((channel): channel is string => !!channel);

      for (const channel of channels) {
        const subscription = client.private(channel);

        for (const event of EVENTS) {
          subscription.listen(event, merge);
        }
      }

      return () => channels.forEach((channel) => client.leave(channel));
    }

    let stopped = false;

    const timer = window.setInterval(async () => {
      await Promise.all(
        pending.map(async (item) => {
          if (!item.links.show) {
            return;
          }

          const response = await fetch(item.links.show, {
            headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
            credentials: "same-origin",
          });

          if (!response.ok || stopped) {
            return;
          }

          const { data } = (await response.json()) as { data: ImportData };

          merge(data);
        }),
      );
    }, options.pollInterval);

    return () => {
      stopped = true;
      window.clearInterval(timer);
    };
    // Re-subscribe only when the set of unfinished imports changes
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [unfinished, options.broadcasting, options.pollInterval]);

  return exports;
}
