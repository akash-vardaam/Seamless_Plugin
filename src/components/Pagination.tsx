import React from 'react';

interface PaginationProps {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
  showPageNumbers?: boolean;
}

export const Pagination: React.FC<PaginationProps> = ({
  currentPage,
  totalPages,
  onPageChange,
  showPageNumbers = false,
}) => {
  if (totalPages <= 1) return null;

  return (
    <div className="seamless-pagination">
      {/* Previous Button */}
      <span
        onClick={() => onPageChange(currentPage - 1)}
        style={{
          opacity: currentPage <= 1 ? 0.5 : 1,
          color: currentPage <= 1 ? '#ccc' : 'inherit',
          cursor: currentPage <= 1 ? 'not-allowed' : 'pointer',
        }}
        className={`seamless-pagination-button-prev ${
          currentPage <= 1
            ? 'seamless-pagination-button-prev:disabled'
            : ''
        }`}
      >
        Previous
      </span>

      {/* Page Numbers */}
      {showPageNumbers && (
        <div className="seamless-pagination-numbers">
          {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
            <span
              key={page}
              onClick={() => onPageChange(page)}
              className={`seamless-pagination-number-button ${
                currentPage === page
                  ? 'seamless-pagination-number-button-current'
                  : 'seamless-pagination-number-button-inactive'
              }`}
            >
              {page}
            </span>
          ))}
        </div>
      )}

      {/* Next Button */}
      <span
        onClick={() => onPageChange(currentPage + 1)}
        style={{
          opacity: currentPage >= totalPages ? 0.5 : 1,
          color: currentPage >= totalPages ? '#ccc' : 'inherit',
          cursor: currentPage >= totalPages ? 'not-allowed' : 'pointer',
        }}
        className={`seamless-pagination-button-next ${
          currentPage >= totalPages
            ? 'seamless-pagination-button-next:disabled'
            : ''
        }`}
      >
        Next
      </span>
    </div>
  );
};
