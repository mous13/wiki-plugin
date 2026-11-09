import { Controller } from '@hotwired/stimulus';

const INFOBOX = /^(`{3,}|~{3,})[ \t]*infobox\b[^\n]*\n([\s\S]*?)^\1[ \t]*$/m;
const CONTENTS = /^[ \t]*\{\{\s*contents(?:\(\s*\))?\s*\}\}[ \t]*\n?/m;
const clean = (value) => value.replace(/\s*\n\s*/g, ' ').trim();

export default class extends Controller {
    static targets = ['source', 'file', 'infoboxDialog', 'infoboxTitle', 'infoboxImage', 'infoboxFile', 'infoboxRows', 'infoboxRow'];
    static values = { uploadUrl: String };

    wrap({ params: { before, after } }) {
        const { selectionStart: start, selectionEnd: end, value } = this.sourceTarget;
        const selected = value.slice(start, end);
        this.replace(start, end, `${before}${selected}${after}`, start + before.length + selected.length);
    }

    insertContents() {
        const source = this.sourceTarget;
        let position = source.selectionStart;
        const existing = source.value.match(CONTENTS);
        if (existing) {
            source.value = source.value.slice(0, existing.index) + source.value.slice(existing.index + existing[0].length);
            if (position > existing.index) {
                position = Math.max(existing.index, position - existing[0].length);
            }
        }

        const tag = `${position > 0 ? '\n\n' : ''}{{ contents }}\n\n`;
        this.replace(position, position, tag, position + tag.length);
    }

    openInfobox() {
        const fields = this.readInfobox();
        this.infoboxTitleTarget.value = fields.title;
        this.infoboxImageTarget.value = fields.image;
        this.infoboxRowsTarget.replaceChildren();
        fields.rows.forEach(([label, value]) => this.appendRow(label, value));
        if (fields.rows.length === 0) {
            this.appendRow();
        }

        this.infoboxDialogTarget.showModal();
    }

    closeInfobox() {
        this.infoboxDialogTarget.close();
    }

    addInfoboxRow() {
        this.appendRow().querySelector('input').focus();
    }

    removeInfoboxRow({ currentTarget }) {
        currentTarget.parentElement.remove();
    }

    pickInfoboxImage() {
        this.infoboxFileTarget.click();
    }

    async uploadInfoboxImage() {
        const [file] = this.infoboxFileTarget.files;
        const url = file ? await this.uploadFile(file) : null;
        this.infoboxFileTarget.value = '';
        if (url) {
            this.infoboxImageTarget.value = url;
        }
    }

    saveInfobox() {
        const lines = [];
        const title = clean(this.infoboxTitleTarget.value);
        const image = clean(this.infoboxImageTarget.value);
        if (title) {
            lines.push(`title: ${title}`);
        }
        if (image) {
            lines.push(`image: ${image}`);
        }

        this.infoboxRowsTarget.querySelectorAll('[data-infobox-row]').forEach((row) => {
            const [labelInput, valueInput] = row.querySelectorAll('input');
            const label = clean(labelInput.value).replace(/:/g, '');
            const value = clean(valueInput.value);
            if (label && value) {
                lines.push(`${['title', 'image'].includes(label) ? label[0].toUpperCase() + label.slice(1) : label}: ${value}`);
            }
        });

        this.closeInfobox();
        if (lines.length === 0) {
            return;
        }

        const block = `\`\`\`infobox\n${lines.join('\n')}\n\`\`\``;
        const existing = this.sourceTarget.value.match(INFOBOX);
        if (existing) {
            this.replace(existing.index, existing.index + existing[0].length, block, existing.index + block.length);
        } else {
            this.replace(0, 0, `${block}\n\n`, block.length + 2);
        }
    }

    pickImage() {
        this.fileTarget.click();
    }

    async upload() {
        const [file] = this.fileTarget.files;
        const url = file ? await this.uploadFile(file) : null;
        this.fileTarget.value = '';
        if (!url) {
            return;
        }

        const { selectionStart: start, selectionEnd: end } = this.sourceTarget;
        const markdown = `![${file.name.replace(/[[\]]/g, '')}](${url})`;
        this.replace(start, end, markdown, start + markdown.length);
    }

    async uploadFile(file) {
        const body = new FormData();
        body.append('file', file);
        const response = await fetch(this.uploadUrlValue, { method: 'POST', body });

        return response.ok ? (await response.json()).url : null;
    }

    readInfobox() {
        const fields = { title: '', image: '', rows: [] };
        const existing = this.sourceTarget.value.match(INFOBOX);
        if (!existing) {
            return fields;
        }

        existing[2].split('\n').forEach((line) => {
            const colon = line.indexOf(':');
            if (colon < 1) {
                return;
            }

            const label = line.slice(0, colon).trim();
            const value = line.slice(colon + 1).trim();
            if (label === 'title' || label === 'image') {
                fields[label] = value;
            } else {
                fields.rows.push([label, value]);
            }
        });

        return fields;
    }

    appendRow(label = '', value = '') {
        const row = this.infoboxRowTarget.content.firstElementChild.cloneNode(true);
        const [labelInput, valueInput] = row.querySelectorAll('input');
        labelInput.value = label;
        valueInput.value = value;
        this.infoboxRowsTarget.append(row);

        return row;
    }

    replace(start, end, text, caret) {
        const source = this.sourceTarget;
        source.setRangeText(text, start, end);
        source.focus();
        source.setSelectionRange(caret, caret);
        source.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
