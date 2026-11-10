import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { ColumnMapping } from "../../resources/js/components/ColumnMapping";
import { FailuresTable } from "../../resources/js/components/FailuresTable";
import { ImportCounts } from "../../resources/js/components/ImportCounts";
import { ImportProgress } from "../../resources/js/components/ImportProgress";
import { PreviewTable } from "../../resources/js/components/PreviewTable";
import { makeImport, makePreview, strings } from "./fixtures";

const duplicateOptions = [
  { value: "skip" as const, label: "ކުރިން ހުރި ރެކޯޑްތައް ދޫކޮށްލާ" },
  { value: "update" as const, label: "ކުރިން ހުރި ރެކޯޑްތައް އަޕްޑޭޓްކުރޭ" },
];

describe("ImportProgress", () => {
  it("shows the progress and the rows", () => {
    render(<ImportProgress import={makeImport({ processed_rows: 1500, total_rows: 2000, progress: 75 })} strings={strings} />);

    expect(screen.getByRole("progressbar").getAttribute("aria-valuenow")).toBe("75");
    expect(screen.getByText("1,500 of 2,000 rows")).toBeTruthy();
  });

  it("is indeterminate while the rows are counted", () => {
    render(<ImportProgress import={makeImport({ progress: null, total_rows: null })} strings={strings} />);

    expect(screen.getByRole("progressbar").getAttribute("aria-valuenow")).toBeNull();
  });
});

describe("ImportCounts", () => {
  it("shows each count", () => {
    const { container } = render(<ImportCounts import={makeImport({ created_rows: 1200 })} strings={strings} />);

    expect(container.querySelector("[data-import-count=created_rows]")!.textContent).toBe("1,200");
    expect(container.querySelector("[data-import-count=failed_rows]")!.className).toContain("text-destructive");
    expect(screen.getByText("ދޫކޮށްލެވުނު")).toBeTruthy();
  });
});

describe("ColumnMapping", () => {
  it("lists the headings with samples and a field select for each, chosen from the mapping", () => {
    const { container } = render(
      <ColumnMapping import={makeImport({ is_draft: true, status: "draft" })} preview={makePreview()} duplicateOptions={duplicateOptions} strings={strings} />,
    );

    expect(screen.getByText("ފަންކާ")).toBeTruthy();
    expect(container.querySelector<HTMLSelectElement>("select[name='mapping[0]']")!.value).toBe("sku");
    expect(container.querySelector<HTMLSelectElement>("select[name='mapping[2]']")!.value).toBe("");
    expect(screen.getAllByText("SKU *").length).toBeGreaterThan(0);
    expect(container.querySelector("form")!.getAttribute("action")).toBe("/imports/9d3c/run");
    expect(container.querySelector("button[name=intent][value=preview]")).toBeTruthy();
  });

  it("warns about required fields that aren't mapped and won't run", () => {
    const preview = makePreview({ missing: ["name"], ready: false });

    const { container } = render(
      <ColumnMapping import={makeImport({ is_draft: true, status: "draft" })} preview={preview} duplicateOptions={duplicateOptions} strings={strings} />,
    );

    expect(screen.getByText(/މި ލާޒިމް ފީލްޑުތަކަށް/).textContent).toContain("ނަން");
    const run = Array.from(container.querySelectorAll("button")).find((button) => button.textContent === strings.run)!;
    expect(run.hasAttribute("disabled")).toBe(true);
  });
});

describe("PreviewTable", () => {
  it("shows the checked rows with their errors or outcome", () => {
    const { container } = render(<PreviewTable preview={makePreview()} strings={strings} />);

    expect(screen.getByText("ފުރަތަމަ 2 ރޯ")).toBeTruthy();
    expect(screen.getByText("ނަން ލާޒިމް")).toBeTruthy();
    expect(container.querySelector("[data-import-preview-row='3']")!.className).toContain("bg-destructive/5");
    expect(screen.getAllByText("އާ").length).toBeGreaterThan(0);
    expect(screen.queryByText("އަގު")).toBeNull();
  });
});

describe("FailuresTable", () => {
  it("lists failed rows and renders nothing without failures", () => {
    render(<FailuresTable failures={[{ row: 7, errors: {}, messages: ["ނަން ލާޒިމް"], values: [] }]} strings={strings} />);

    expect(screen.getByText("ނަން ލާޒިމް")).toBeTruthy();

    const { container } = render(<FailuresTable failures={[]} strings={strings} />);
    expect(container.innerHTML).toBe("");
  });
});
