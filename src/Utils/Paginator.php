<?php

declare(strict_types=1);

namespace Hizpro\PhpSpark\Utils;

use InvalidArgumentException;

/**
 * Generates pagination navigation page numbers
 */
final class Paginator
{
    private const string ELLIPSIS = '&hellip;';

    /**
     * @var int
     */
    private int $totalRows;
    /**
     * @var int
     */
    private int $pageSize;
    /**
     * @var int
     */
    private int $pageNumber;

    private int $totalPages;

    /**
     * PageNumberGenerator constructor.
     *
     * @param int $totalRows
     * @param int $pageSize
     * @param int $pageNumber
     */
    public function __construct(int $totalRows, int $pageSize, int $pageNumber = 1)
    {
        if ($totalRows < 0) {
            throw new InvalidArgumentException('Total rows cannot be less than 0');
        }
        $this->totalRows = $totalRows;

        if ($pageSize < 1) {
            throw new InvalidArgumentException('Page size cannot be less than 1');
        }
        $this->pageSize = $pageSize;

        $this->totalPages = max(1, (int)ceil($this->totalRows / $this->pageSize));

        if ($pageNumber < 1) {
            throw new InvalidArgumentException('Page number cannot be less than 1');
        }
        $this->pageNumber = $pageNumber;
    }

    /**
     * Get the total number of rows.
     *
     * @return int
     */
    public function totalRows(): int
    {
        return $this->totalRows;
    }

    /**
     * Get the page size.
     *
     * @return int
     */
    public function pageSize(): int
    {
        return $this->pageSize;
    }

    /**
     * Get the limit for the query.
     *
     * @return int
     */
    public function limitForQuery(): int
    {
        return $this->pageSize;
    }

    /**
     * Get the offset for the query.
     *
     * @return int
     */
    public function offsetForQuery(): int
    {
        return $this->pageSize * ($this->pageNumber - 1);
    }

    /**
     * Get the total number of pages.
     *
     * @return int
     */
    public function totalPages(): int
    {
        return $this->totalPages;
    }

    /**
     * Get the current page number.
     *
     * @return int
     */
    public function currentPage(): int
    {
        return min($this->pageNumber, $this->totalPages);
    }

    /**
     * Get the previous page number.
     *
     * @return int|null
     */
    public function prevNumber(): ?int
    {
        $currentPage = $this->currentPage();
        return ($currentPage > 1) ? $currentPage - 1 : null;
    }

    /**
     * Get the next page number.
     *
     * @return int|null
     */
    public function nextNumber(): ?int
    {
        $currentPage = $this->currentPage();
        return ($currentPage < $this->totalPages) ? $currentPage + 1 : null;
    }

    /**
     * Get an array of page numbers to display for pagination.
     *
     * @param int $showLength
     * @return array<int, string|int>
     */
    public function pageNumbers(int $showLength = 7): array
    {
        if ($showLength % 2 === 0) {
            throw new InvalidArgumentException('Page numbers show length must be an odd number');
        }

        if ($showLength < 1) {
            throw new InvalidArgumentException('Page numbers show length cannot be less than 1');
        }

        $currentPage = $this->currentPage();

        if ($this->totalPages <= $showLength) {
            return range(1, $this->totalPages);
        }

        if ($showLength == 1) {
            return [$currentPage];
        }

        if ($showLength == 3) {
            if (in_array($currentPage, [1, $this->totalPages])) {
                return [1, self::ELLIPSIS, $this->totalPages];
            }

            return [1, $currentPage, $this->totalPages];
        }

        $offsetLength = (int)(($showLength - 5) / 2);
        $symmetricalLength = (int)(($showLength - 1) / 2);

        if ($currentPage <= 1 + $symmetricalLength) {
            return array_merge(
                range(1, 1 + $symmetricalLength + $offsetLength),
                [self::ELLIPSIS, $this->totalPages]
            );
        }

        if ($currentPage >= $this->totalPages - $symmetricalLength) {
            return array_merge(
                [1, self::ELLIPSIS],
                range($this->totalPages - $symmetricalLength - $offsetLength, $this->totalPages)
            );
        }

        return array_merge(
            [1, self::ELLIPSIS],
            range($currentPage - $offsetLength, $currentPage + $offsetLength),
            [self::ELLIPSIS, $this->totalPages]
        );
    }
}
