import React, { useState, useEffect } from "react";

export function CurrencyInput({ value, onChange, placeholder, className, required, disabled, name, id }) {
  const [displayValue, setDisplayValue] = useState("");

  useEffect(() => {
    if (value === null || value === undefined || value === "") {
      setDisplayValue("");
    } else {
      const numStr = value.toString().replace(/[^0-9]/g, "");
      if (numStr) {
        setDisplayValue("Rp " + Number(numStr).toLocaleString("id-ID"));
      } else {
        setDisplayValue("");
      }
    }
  }, [value]);

  const handleChange = (e) => {
    let rawStr = e.target.value.replace(/[^0-9]/g, "");
    
    if (rawStr === "") {
      setDisplayValue("");
      onChange("");
      return;
    }

    const formatted = "Rp " + Number(rawStr).toLocaleString("id-ID");
    setDisplayValue(formatted);
    
    onChange(rawStr);
  };

  return (
    <input
      id={id}
      name={name}
      type="text"
      inputMode="numeric"
      value={displayValue}
      onChange={handleChange}
      placeholder={placeholder || "Rp 0"}
      className={className}
      required={required}
      disabled={disabled}
      autoComplete="off"
    />
  );
}

