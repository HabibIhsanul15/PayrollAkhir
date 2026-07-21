function normalizeBoolean(value) {
  const raw = String(value ?? "").trim().toLowerCase();
  return ["1", "true", "yes", "ya"].includes(raw);
}

function normalizeExpectedValue(field, value) {
  if (field === "num_toddlers") {
    return Number(value ?? 0);
  }

  if (field === "is_trainer" || field === "is_on_probation") {
    return normalizeBoolean(value);
  }

  return value;
}

function getActualValue(field, form) {
  if (field === "num_toddlers") {
    return Number(form?.num_toddlers ?? 0);
  }

  if (field === "is_trainer") {
    return !!form?.is_trainer;
  }

  if (field === "is_on_probation") {
    return !!form?.is_on_probation;
  }

  return form?.[field];
}

function compareCondition(actual, expected, operator) {
  switch (operator) {
    case "!=":
      return actual !== expected;
    case ">":
      return Number(actual) > Number(expected);
    case ">=":
      return Number(actual) >= Number(expected);
    case "<":
      return Number(actual) < Number(expected);
    case "<=":
      return Number(actual) <= Number(expected);
    case "=":
    default:
      return actual === expected;
  }
}

export function getAllowanceConditionStatus(allowanceType, form) {
  const field = allowanceType?.condition_field;

  if (!field) {
    return {
      eligible: true,
      helperText: "Tanpa syarat khusus.",
    };
  }

  if (field === "is_on_probation") {
    return {
      eligible: false,
      helperText: "Diatur lewat flow promosi atau demosi.",
    };
  }

  const operator = allowanceType?.condition_operator || "=";
  const expected = normalizeExpectedValue(field, allowanceType?.condition_value);
  const actual = getActualValue(field, form);
  const eligible = compareCondition(actual, expected, operator);

  if (eligible) {
    return {
      eligible: true,
      helperText: "Syarat terpenuhi.",
    };
  }

  if (field === "num_toddlers") {
    return {
      eligible: false,
      helperText: `Butuh jumlah balita ${operator} ${expected}.`,
    };
  }

  if (field === "is_trainer") {
    return {
      eligible: false,
      helperText: "Butuh flag trainer aktif.",
    };
  }

  return {
    eligible: false,
    helperText: "Syarat belum terpenuhi.",
  };
}
