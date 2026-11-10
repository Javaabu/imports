import { describe, expect, it } from "vitest";
import { rowsText, updateRow } from "../../resources/js/imports";
import { makeImport } from "./fixtures";

describe("Blade progress script", () => {
  it("updates a row and its counts from a progress payload", () => {
    document.body.innerHTML = `
      <div data-import-row="9d3c" data-import-finished="false">
        <span data-import-status><span data-slot="status-badge">ތައްޔާރުކުރަނީ</span></span>
        <div role="progressbar" aria-valuenow="25"><div data-import-progress-bar class="animate-pulse" style="inline-size: 25%"></div></div>
        <span data-import-progress-text>25%</span>
        <span data-import-rows></span>
        <dd data-import-count="created_rows">40</dd>
        <dd data-import-count="failed_rows">2</dd>
      </div>`;

    const row = document.querySelector<HTMLElement>("[data-import-row]")!;

    updateRow(
      row,
      makeImport({ progress: 100, processed_rows: 200, created_rows: 1190, failed_rows: 10, status_label: "ނިމިއްޖެ", is_finished: true }),
      ":total ރޯގެ ތެރެއިން :processed",
    );

    expect(row.querySelector<HTMLElement>("[data-import-progress-bar]")!.style.inlineSize).toBe("100%");
    expect(row.querySelector("[data-import-progress-text]")!.textContent).toBe("100%");
    expect(row.querySelector("[role=progressbar]")!.getAttribute("aria-valuenow")).toBe("100");
    expect(row.querySelector("[data-import-rows]")!.textContent).toBe("200 ރޯގެ ތެރެއިން 200");
    expect(row.querySelector("[data-import-count=created_rows]")!.textContent).toBe("1,190");
    expect(row.querySelector("[data-import-count=failed_rows]")!.textContent).toBe("10");
    expect(row.querySelector("[data-slot=status-badge]")!.textContent).toBe("ނިމިއްޖެ");
    expect(row.dataset.importFinished).toBe("true");
  });

  it("formats the rows text", () => {
    expect(rowsText(":processed / :total", 10, 20)).toBe("10 / 20");
    expect(rowsText(":processed / :total", 10, null)).toBe("");
  });
});
