<?php

namespace App\Exports;

use App\Constants\FineConstant;
use App\Models\Fine;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;

class FineExport extends DefaultValueBinder implements
    FromCollection,
    WithHeadings,
    WithMapping,
    WithColumnWidths,
    WithStyles,
    WithCustomValueBinder
{
    protected array $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function collection()
    {
        $page = (int) ($this->params['page'] ?? 1);
        $perPage = (int) ($this->params['per_page'] ?? 15);

        $query = Fine::query()
            ->with([
                'staff',
                'cause',
                'orderPackage'
            ]);

        // Filter
        if (!empty($this->params['order_code'])) {
            $query->where('order_code', $this->params['order_code']);
        }

        if (!empty($this->params['user_id'])) {
            $query->where('user_id', $this->params['user_id']);
        }

        if (!empty($this->params['cause_id'])) {
            $query->where('cause_id', $this->params['cause_id']);
        }

        if (!empty($this->params['type'])) {
            $query->where('type', $this->params['type']);
        }

        if (!empty($this->params['status'])) {
            $query->where('status', $this->params['status']);
        }

        if (!empty($this->params['code_po'])) {
            $query->whereHas('source', function ($q) {
                $q->where('code_po', $this->params['code_po']);
            });
        }

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->getCollection();
    }

    public function headings(): array
    {
        return [
            'Ngày tạo',
            'Mã đơn hàng',
            'Mã vận đơn',
            'Nhân viên bị phạt',
            'Người tạo',
            'Loại phạt',
            'Số tiền',
            'Trạng thái',
            'Số ý kiến',
            'Nguyên nhân',
            'Giải pháp',
        ];
    }

    public function map($fine): array
    {
        return [
            optional($fine->created_at)->format('d/m/Y H:i:s'),
            $fine->order_code,
            $fine->bill_code,
            optional($fine->staff)->name,
            optional($fine->cause)->name,
            FineConstant::TYPES[$fine->type] ?? '',
            $fine->amount,
            FineConstant::STATUSES[$fine->status] ?? '',
            $fine->comment_number,
            $fine->reason,
            $fine->solution,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20, // Ngày tạo
            'B' => 20, // Mã đơn hàng
            'C' => 20, // Mã vận đơn
            'D' => 25, // Nhân viên bị phạt
            'E' => 25, // Người tạo
            'F' => 20, // Loại phạt
            'G' => 15, // Số tiền
            'H' => 20, // Trạng thái
            'I' => 15, // Số bình luận
            'J' => 40, // Nguyên nhân
            'K' => 60, // Giải pháp
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Header đậm
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        // Tự xuống dòng cho cột Nguyên nhân và Giải pháp
        $sheet->getStyle('J:K')
            ->getAlignment()
            ->setWrapText(true);

        return [];
    }

    public function bindValue(Cell $cell, $value)
    {
        if ($cell->getColumn() === 'C') {
            $cell->setValueExplicit(
                (string) $value,
                DataType::TYPE_STRING
            );

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}

