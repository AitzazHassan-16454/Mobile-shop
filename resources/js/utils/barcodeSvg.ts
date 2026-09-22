/**
 * Utility to generate a clean, responsive Code128 / Code39 style SVG Barcode for invoices.
 */

// Code39 pattern table for alphanumeric barcodes
const CODE39_PATTERNS: Record<string, string> = {
    '0': '101001101101', '1': '110100101011', '2': '101100101011', '3': '110110010101',
    '4': '101001101011', '5': '110100110101', '6': '101100110101', '7': '101001011011',
    '8': '110100101101', '9': '101100101101', 'A': '110101001011', 'B': '101101001011',
    'C': '110110100101', 'D': '101011001011', 'E': '110101100101', 'F': '101101100101',
    'G': '101010011011', 'H': '110101001101', 'I': '101101001101', 'J': '101011001101',
    'K': '110101010011', 'L': '101101010011', 'M': '110110101001', 'N': '101011010011',
    'O': '110101101001', 'P': '101101101001', 'Q': '101010110011', 'R': '110101011001',
    'S': '101101011001', 'T': '101011011001', 'U': '110010101011', 'V': '100110101011',
    'W': '110011010101', 'X': '100101101011', 'Y': '110010110101', 'Z': '100110110101',
    '-': '100101011011', '.': '110010101101', ' ': '100110101101', '*': '100101101101',
    '$': '100100100101', '/': '100100101001', '+': '100101001001', '%': '100100100101',
};

export function generateBarcodeSvg(value: string, height = 40): string {
    const rawValue = `*${value.toUpperCase().replace(/[^A-Z0-9\-\.\ \$\/\+\%]/g, '')}*`;
    let bitPattern = '';

    for (let i = 0; i < rawValue.length; i++) {
        const char = rawValue[i];
        const pattern = CODE39_PATTERNS[char] || CODE39_PATTERNS[' '];
        bitPattern += pattern + '0';
    }

    const barWidth = 2;
    const svgWidth = bitPattern.length * barWidth;
    let rects = '';

    for (let i = 0; i < bitPattern.length; i++) {
        if (bitPattern[i] === '1') {
            const x = i * barWidth;
            rects += `<rect x="${x}" y="0" width="${barWidth}" height="${height}" fill="#000000" />`;
        }
    }

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${svgWidth} ${height}" width="100%" height="${height}" preserveAspectRatio="none">${rects}</svg>`;
}
