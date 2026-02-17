import React, { useState, useEffect, useMemo } from 'react';
import debounce from 'lodash.debounce';

interface SearchInputProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  delay?: number;
}

export const SearchInput: React.FC<SearchInputProps> = ({
  value,
  onChange,
  placeholder = 'Search event by name',
  delay = 500,
}) => {
  const [localValue, setLocalValue] = useState(value);

  // Sync local state if parent value changes externally
  useEffect(() => {
    setLocalValue(value);
  }, [value]);

  // Create a debounced version of the onChange prop
  const debouncedOnChange = useMemo(
    () => debounce((nextValue: string) => {
      onChange(nextValue);
    }, delay),
    [onChange, delay]
  );

  // Clean up debounce on unmount
  useEffect(() => {
    return () => {
      debouncedOnChange.cancel();
    };
  }, [debouncedOnChange]);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = e.target.value;
    setLocalValue(newValue);
    debouncedOnChange(newValue);
  };

  return (
    <input
      type="text"
      value={localValue}
      onChange={handleChange}
      placeholder={placeholder}
      className="seamless-search-input"
    />
  );
};
