
import React, { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import '../styles/seamless-accordion.css';

interface AccordionItem {
    title: string;
    content: React.ReactNode;
}

interface SeamlessAccordionProps {
    items: AccordionItem[];
}

export const SeamlessAccordion: React.FC<SeamlessAccordionProps> = ({ items }) => {
    const [activeIndices, setActiveIndices] = useState<number[]>([]);

    const toggleSection = (idx: number) => {
        setActiveIndices(prev =>
            prev.includes(idx)
                ? prev.filter(i => i !== idx)
                : [...prev, idx]
        );
    };

    return (
        <div className="seamless-accordions">
            {items.map((section, idx) => (
                <div key={idx} className="seamless-accordion-item">
                    <button
                        className="seamless-accordion-trigger"
                        onClick={() => toggleSection(idx)}
                    >
                        {activeIndices.includes(idx) ? <ChevronUp size={20} className="seamless-accordion-icon" /> : <ChevronDown size={20} className="seamless-accordion-icon" />}
                        <span className="seamless-accordion-title">{section.title}</span>
                    </button>
                    {activeIndices.includes(idx) && (
                        <div className="seamless-accordion-content">
                            {section.content}
                        </div>
                    )}
                </div>
            ))}
        </div>
    );
};
