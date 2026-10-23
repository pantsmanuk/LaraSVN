export function languageForFilename(filename, languages) {
    const name = filename.split('/').at(-1).toLowerCase();
    const dot = name.lastIndexOf('.');
    if (dot <= 0) return 'plain';
    const extension = name.slice(dot + 1);
    return Object.entries(languages).find(([, extensions]) => extensions.includes(extension))?.[0] ?? 'plain';
}
