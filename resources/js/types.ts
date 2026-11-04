export type ImportStatus = "draft" | "pending" | "processing" | "completed" | "failed" | "cancelled";

export type ImportStatusColor = "secondary" | "info" | "success" | "danger" | "warning";

export type RowOutcome = "created" | "updated" | "skipped";

/** What the progress events broadcast, and the subset of an import the pages update */
export interface ImportProgressPayload {
  id: string;
  status: ImportStatus;
  status_label: string;
  status_color: ImportStatusColor;
  progress: number | null;
  total_rows: number | null;
  processed_rows: number;
  created_rows: number;
  updated_rows: number;
  skipped_rows: number;
  failed_rows: number;
  is_finished: boolean;
  has_report: boolean;
  error: string | null;
}

/** An import as ImportResource serializes it */
export interface ImportData extends ImportProgressPayload {
  type: string | null;
  name: string;
  filename: string;
  format: "csv" | "xlsx" | "ods";
  size: number | null;
  duplicates: "skip" | "update" | "fail";
  dry_run: boolean;
  is_draft: boolean;
  is_cancellable: boolean;
  user?: { type: string; id: string | number; name: string | null } | null;
  can: { update: boolean; download: boolean; cancel: boolean; delete: boolean };
  links: {
    show: string | null;
    preview: string | null;
    run: string | null;
    report: string | null;
    cancel: string | null;
    destroy: string | null;
  };
  broadcast_channel: string | null;
  started_at: string | null;
  completed_at: string | null;
  failed_at: string | null;
  cancelled_at: string | null;
  expires_at: string | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface PreviewHeading {
  index: number;
  heading: string;
  samples: string[];
  attribute: string | null;
}

export interface PreviewColumn {
  attribute: string;
  label: string;
  description: string | null;
  required: boolean;
  aliases: string[];
  heading: number | null;
}

export interface PreviewRow {
  row: number;
  values: Record<string, string>;
  errors: Record<string, string[]>;
  outcome: RowOutcome | null;
  outcome_label: string | null;
}

/** What Preview::toArray() returns for a draft */
export interface PreviewData {
  format: string;
  headings: PreviewHeading[];
  columns: PreviewColumn[];
  mapping: Record<string, string>;
  missing: string[];
  ready: boolean;
  rows: PreviewRow[];
  counts: { checked: number; invalid: number; created: number; updated: number; skipped: number };
}

export interface RowFailureData {
  row: number;
  errors: Record<string, string[]>;
  messages: string[];
  values: unknown[];
}

export interface ImportRoutes {
  index: string;
  store: string;
  template: string;
  show: string;
  preview: string;
  run: string;
  report: string;
  cancel: string;
  destroy: string;
}

export interface Option<T extends string = string> {
  value: T;
  label: string;
}

export interface ImportsStrings {
  title: string;
  new_import: string;
  import: string;
  file: string;
  upload: string;
  templates: string;
  download_template: string;
  status: string;
  all_statuses: string;
  only_mine: string;
  filter: string;
  name: string;
  progress: string;
  rows: string;
  started_by: string;
  created: string;
  actions: string;
  view: string;
  cancel: string;
  delete: string;
  confirm_cancel: string;
  confirm_delete: string;
  no_imports: string;
  no_imports_message: string;
  rows_of: string;
  back: string;
  map_columns: string;
  map_columns_help: string;
  column_in_file: string;
  sample_values: string;
  field: string;
  dont_import: string;
  required: string;
  missing_required: string;
  existing_records: string;
  dry_run: string;
  preview: string;
  preview_help: string;
  row: string;
  errors: string;
  result: string;
  no_errors: string;
  run: string;
  run_dry: string;
  retry: string;
  created_rows: string;
  updated_rows: string;
  skipped_rows: string;
  failed_rows: string;
  failed_rows_help: string;
  download_report: string;
  dry_run_notice: string;
  error: string;
  file_unavailable: string;
  file_help: string;
}

export interface Paginated<T> {
  data: T[];
  links: Record<string, string | null>;
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    path: string;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
}

/** Props shared by the pages */
export interface ImportsPageProps {
  title: string;
  broadcasting: boolean;
  poll_interval: number;
  routes: ImportRoutes;
  urls: { index: string; store: string };
  strings: ImportsStrings;
}

export interface AvailableImporter {
  type: string;
  label: string;
  templates: Record<string, string>;
}

export interface ImportsIndexProps extends ImportsPageProps {
  imports: Paginated<ImportData>;
  available: AvailableImporter[];
  formats: Option[];
  accept: string;
  /** The upload limit in kilobytes */
  max_file_size: number;
  statuses: Option<ImportStatus>[];
  sees_others: boolean;
  filters: { status: ImportStatus | null; type: string | null; mine: boolean };
}

export interface ImportsShowProps extends ImportsPageProps {
  import: ImportData;
  preview: (PreviewData & { error?: undefined }) | { error: string } | null;
  failures: RowFailureData[];
  duplicate_options: Option<ImportData["duplicates"]>[];
}
