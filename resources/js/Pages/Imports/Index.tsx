import { Card, Checkbox, File, Form, Pagination, Select, Status, Submit, Table, TableCell, TableEmptyRow, TableHeading, TableRow } from "@javaabu/forms";
import { router } from "@inertiajs/react";
import { ImportActions } from "../../components/ImportActions";
import { ImportProgress } from "../../components/ImportProgress";
import { useImportProgress } from "../../hooks/useImportProgress";
import type { ImportsIndexProps } from "../../types";

/** imports::Imports/Index — upload a file, download templates, and the user's imports with live progress */
export default function Index(props: ImportsIndexProps) {
  const { strings, urls, sees_others: seesOthers } = props;

  const imports = useImportProgress(props.imports.data, {
    broadcasting: props.broadcasting,
    pollInterval: props.poll_interval,
    onFinished: () => router.reload({ only: ["imports"] }),
  });

  const columns = seesOthers ? 6 : 5;

  return (
    <div className="flex flex-col gap-6" data-imports>
      <h1 className="text-2xl font-semibold">{props.title}</h1>

      {props.available.length > 0 ? (
        <Card
          title={strings.new_import}
          footer={
            <div className="flex flex-col gap-2 text-sm">
              <span className="text-muted-foreground">{strings.templates}</span>
              <ul className="flex flex-wrap gap-x-6 gap-y-1">
                {props.available.map((importer) => (
                  <li key={importer.type} className="flex flex-wrap items-center gap-2">
                    <span className="font-medium">{importer.label}</span>
                    {Object.entries(importer.templates).map(([format, url]) => (
                      <a key={format} href={url} className="text-primary hover:underline" download>
                        {format.toUpperCase()}
                      </a>
                    ))}
                  </li>
                ))}
              </ul>
            </div>
          }
        >
          <Form method="POST" action={urls.store} files className="flex flex-wrap items-end gap-4" data-imports-upload>
            <Select
              name="type"
              label={strings.import}
              options={Object.fromEntries(props.available.map((option) => [option.type, option.label]))}
              required
            />
            <File
              name="file"
              label={strings.file}
              mimetypes={props.accept.split(",")}
              extensions={props.accept.split(",").map((extension) => extension.replace(/^\./, ""))}
              maxSize={props.max_file_size}
              help={strings.file_help}
              required
            />
            <Submit>{strings.upload}</Submit>
          </Form>
        </Card>
      ) : null}

      <Form method="GET" action={urls.index} model={props.filters} className="flex flex-wrap items-end gap-4">
        <Select
          name="status"
          label={strings.status}
          placeholder={strings.all_statuses}
          showPlaceholder
          options={Object.fromEntries(props.statuses.map((option) => [option.value, option.label]))}
        />
        {seesOthers ? <Checkbox name="mine" value="1" label={strings.only_mine} /> : null}
        <Submit color="secondary" animate={false}>
          {strings.filter}
        </Submit>
      </Form>

      <Table
        model="imports"
        noCheckbox
        noBulk
        headers={
          <>
            <TableHeading label={strings.name} />
            <TableHeading label={strings.status} />
            <TableHeading label={strings.progress} />
            {seesOthers ? <TableHeading label={strings.started_by} /> : null}
            <TableHeading label={strings.created} />
            <TableHeading label={strings.actions} />
          </>
        }
        pagination={props.imports.meta.last_page > 1 ? <Pagination paginator={props.imports} /> : null}
      >
        {imports.length ? (
          imports.map((item) => (
            <TableRow key={item.id} modelId={item.id} noCheckbox data-import-row={item.id}>
              <TableCell name="name" label={strings.name}>
                {item.links.show ? (
                  <a href={item.links.show} className="font-medium hover:underline">
                    {item.name}
                  </a>
                ) : (
                  item.name
                )}
                <div className="text-muted-foreground text-sm" dir="auto">
                  {item.filename}
                </div>
              </TableCell>
              <TableCell name="status" label={strings.status}>
                <Status color={item.status_color} label={item.status_label} />
              </TableCell>
              <TableCell name="progress" label={strings.progress}>
                <ImportProgress import={item} strings={strings} />
              </TableCell>
              {seesOthers ? (
                <TableCell name="user" label={strings.started_by}>
                  {item.user?.name ?? ""}
                </TableCell>
              ) : null}
              <TableCell name="created_at" label={strings.created}>
                {item.created_at ? <time dateTime={item.created_at}>{new Date(item.created_at).toLocaleString()}</time> : null}
              </TableCell>
              <TableCell name="actions" label={strings.actions}>
                <ImportActions import={item} strings={strings} compact />
              </TableCell>
            </TableRow>
          ))
        ) : (
          <TableEmptyRow columns={columns} noCheckbox>
            <p className="font-medium">{strings.no_imports}</p>
            <p className="text-muted-foreground text-sm">{strings.no_imports_message}</p>
          </TableEmptyRow>
        )}
      </Table>
    </div>
  );
}
