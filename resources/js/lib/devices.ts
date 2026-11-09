import { trans } from 'laravel-vue-i18n';

/**
 * The line under the name of a linked phone, for the panel *Link the phone* and *My account*:
 * «Linked now», or when it was linked and when it last uploaded.
 *
 * @param device - the phone
 * @param date - writes a `yyyy-mm-dd` date the way the app does (`useFormat().date`)
 * @returns the line, in the language of the page
 */
export function deviceSummary(
    device: App.Data.LinkedDeviceData,
    date: (value: string) => string,
): string {
    if (device.linked_now) {
        return trans('Linked now');
    }

    return device.last_upload_on === null
        ? trans('Linked on :date · nothing uploaded yet', {
              date: date(device.linked_on),
          })
        : trans('Linked on :date · last upload on :upload', {
              date: date(device.linked_on),
              upload: date(device.last_upload_on),
          });
}
