/**
 * Normalizes Serbian/Latin names for forgiving searches.
 *
 * Unicode decomposition handles č/ć/š/ž and the explicit replacements cover
 * characters such as đ/ð which do not decompose to their ASCII equivalent.
 */
export const normalizeSearchText = (value: string): string =>
    value
        .trim()
        .toLocaleLowerCase('sr')
        .replace(/[đð]/g, 'd')
        .replace(/ł/g, 'l')
        .replace(/ø/g, 'o')
        .replace(/æ/g, 'ae')
        .replace(/ß/g, 'ss')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
