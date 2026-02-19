
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
    const [activeIndex, setActiveIndex] = useState<number | null>(null);

    const toggleSection = (idx: number) => {
        setActiveIndex(prev => (prev === idx ? null : idx));
    };

    return (
        <div className="seamless-accordions">
            {items.map((section, idx) => {
                const isActive = activeIndex === idx;
                return (
                    <div key={idx} className="seamless-accordion-item">
                        <button
                            className={`seamless-accordion-trigger ${isActive ? 'active' : ''}`}
                            onClick={() => toggleSection(idx)}
                        >
                            {isActive ? <ChevronUp size={20} className="seamless-accordion-icon" /> : <ChevronDown size={20} className="seamless-accordion-icon" />}
                            <span className="seamless-accordion-title">{section.title}</span>
                        </button>
                        {isActive && (
                            <div className="seamless-accordion-content">
                                {section.content}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
};
