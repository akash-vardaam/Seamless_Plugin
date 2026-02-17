import React, { useState, useRef, useEffect } from 'react';

interface CustomDropdownProps {
  value: string;
  onChange: (value: string) => void;
  options: Array<{ value: string; label: string }>;
  placeholder?: string;
  className?: string;
}

export const CustomDropdown: React.FC<CustomDropdownProps> = ({
  value,
  onChange,
  options,
  placeholder = 'Select',
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const selectedOption = options.find(opt => opt.value === value);
  const displayValue = selectedOption ? selectedOption.label : placeholder;

  return (
    <div ref={containerRef} className="seamless-dropdown">
      {/* Button/Trigger */}
      <div
        onClick={() => setIsOpen(!isOpen)}
        className="seamless-dropdown-trigger"
        style={{ fontFamily: 'Montserrat' }}
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            setIsOpen(!isOpen);
          }
        }}
      >
        <span className="truncate">{displayValue}</span>
        <span className="seamless-dropdown-arrow">▼</span>
      </div>

      {/* Dropdown Menu */}
      {isOpen && (
        <div className="seamless-dropdown-menu">
          {/* Header with selected value */}
          <div className="seamless-dropdown-header" style={{ fontFamily: 'Montserrat' }}>
            {displayValue}
          </div>

          {/* Items Container with scroll */}
          <div className="seamless-dropdown-items">
            {options.map((option) => (
              <div
                key={option.value}
                onClick={() => {
                  onChange(option.value);
                  setIsOpen(false);
                }}
                className="seamless-dropdown-item"
                style={{ fontFamily: 'Montserrat' }}
                role="option"
                tabIndex={0}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    onChange(option.value);
                    setIsOpen(false);
                  }
                }}
              >
                {option.label}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
