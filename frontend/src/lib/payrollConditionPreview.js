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
  const operator = allowanceType?.condition_operator;
  const expectedValue = allowanceType?.condition_value;

  if (!field || !operator || expectedValue === null || expectedValue === undefined || expectedValue === "") {
    return {
      eligible: true,
      helperText: "Tanpa syarat khusus.",
    };
  }

  const actualValue = normalizeExpectedValue(field, getActualValue(field, form));
  const normalizedExpectedValue = normalizeExpectedValue(field, expectedValue);
  const eligible = compareCondition(actualValue, normalizedExpectedValue, operator);

  return {
    eligible,
    helperText: eligible
      ? "Syarat tunjangan terpenuhi."
      : `Syarat ${field} ${operator} ${expectedValue} belum terpenuhi.`,
  };
}
