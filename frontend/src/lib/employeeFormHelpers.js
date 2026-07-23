export function digitsOnly(value, maxLength = null) {
  const cleaned = String(value ?? "").replace(/\D+/g, "");

  if (maxLength === null || maxLength === undefined) {
    return cleaned;
  }

  return cleaned.slice(0, maxLength);
}

export function nonNegativeIntegerInput(value, maxLength = null) {
  return digitsOnly(value, maxLength);
}

export function indonesianMobilePhoneInput(value) {
  const digits = digitsOnly(value, 13);

  if (digits === "" || digits === "0" || digits === "08") {
    return digits;
  }

  return /^08[1-9]/.test(digits) ? digits : digits.startsWith("08") ? "08" : "";
}

export function isIndonesianMobilePhone(value) {
  return /^08[1-9][0-9]{7,10}$/.test(String(value ?? ""));
}

function randomIndex(max) {
  if (globalThis.crypto?.getRandomValues) {
    const array = new Uint32Array(1);
    globalThis.crypto.getRandomValues(array);
    return array[0] % max;
  }

  return Math.floor(Math.random() * max);
}

export function generateEmployeeAccountPassword(length = 12) {
  const upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
  const lower = "abcdefghijkmnopqrstuvwxyz";
  const digits = "23456789";
  const all = `${upper}${lower}${digits}`;

  const chars = [
    upper[randomIndex(upper.length)],
    lower[randomIndex(lower.length)],
    digits[randomIndex(digits.length)],
  ];

  while (chars.length < length) {
    chars.push(all[randomIndex(all.length)]);
  }

  for (let i = chars.length - 1; i > 0; i -= 1) {
    const j = randomIndex(i + 1);
    [chars[i], chars[j]] = [chars[j], chars[i]];
  }

  return chars.join("");
}
