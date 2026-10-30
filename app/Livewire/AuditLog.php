<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Audit\AuditLog as AuditLogQuery;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit log (F-14): voucher audits, request audits and web logins in one read-only list for the Financial Manager and Head
 * Office, with filters by person, action, source and date, and a CSV export of what the filters show.
 */
#[Layout('components.layouts.app')]
class AuditLog extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    private const EXPORT_LIMIT = 50000;

    #[Url]
    public string $person = '';

    #[Url]
    public string $action = '';

    #[Url]
    public string $source = '';

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function updated(string $name): void
    {
        if (in_array($name, ['person', 'action', 'source', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function export(AuditLogQuery $log): StreamedResponse
    {
        $rows = $log->query($this->filters())->limit(self::EXPORT_LIMIT)->get();
        $names = $this->names($rows);

        return response()->streamDownload(function () use ($rows, $names) {
            $out = new SplFileObject('php://output', 'w');
            $out->fwrite("\xEF\xBB\xBF");
            $out->fputcsv([__('auditlog.date'), __('auditlog.source'), __('auditlog.person'), __('auditlog.action'), __('auditlog.subject'), __('auditlog.reason')]);

            foreach ($rows as $row) {
                $out->fputcsv([
                    $this->when($row), __('auditlog.sources.'.$row->source), $this->person($row, $names), $this->actionLabel($row->source, (string) $row->action),
                    (string) ($row->subject ?? ''), (string) ($row->reason ?? ''),
                ]);
            }
        }, 'audit-log.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(AuditLogQuery $log): View
    {
        $rows = $log->query($this->filters())->paginate(self::PER_PAGE);
        $names = $this->names(collect($rows->items()));

        return view('livewire.audit-log', [
            'rows' => $rows,
            'people' => $this->people(),
            'actions' => $log->actions(),
            'sources' => AuditLogQuery::SOURCES,
            'present' => fn (\stdClass $row): array => [
                'when' => $this->when($row),
                'person' => $this->person($row, $names),
                'action' => $this->actionLabel($row->source, (string) $row->action),
                'url' => $this->url($row),
            ],
        ]);
    }

    /** @return array{person: string, action: string, source: string, from: string, to: string} */
    private function filters(): array
    {
        $date = fn (string $value): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : '';

        return ['person' => $this->person, 'action' => $this->action, 'source' => $this->source, 'from' => $date($this->from), 'to' => $date($this->to)];
    }

    /** @return Collection<int, User> */
    private function people(): Collection
    {
        return User::query()->orderBy('Username')->get();
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return array<int, string>
     */
    private function names(Collection $rows): array
    {
        $ids = $rows->pluck('person_id')->filter()->unique()->values()->all();
        $names = [];

        foreach (User::query()->whereIn('ID', $ids)->get() as $person) {
            $names[(int) $person->getAuthIdentifier()] = $person->username;
        }

        return $names;
    }

    /** @param  array<int, string>  $names */
    private function person(\stdClass $row, array $names): string
    {
        return $row->person_id !== null ? ($names[(int) $row->person_id] ?? '#'.$row->person_id) : (string) ($row->username ?? '');
    }

    /** A sign-in only has a day, which comes back as midnight: show the day alone. */
    private function when(\stdClass $row): string
    {
        $at = (string) $row->at;

        return str_ends_with($at, ' 00:00:00') ? substr($at, 0, 10) : $at;
    }

    private function actionLabel(string $source, string $action): string
    {
        $key = $source === 'request' ? 'requests.audit_action.'.$action : 'auditlog.voucher_actions.'.$action;

        return Lang::has($key) ? (string) __($key) : $action;
    }

    private function url(\stdClass $row): ?string
    {
        return match ($row->source) {
            'voucher' => route(Navigation::routeName('vouchers'), ['voucher' => $row->subject]),
            'request' => route(Navigation::routeName('requests'), ['request' => $row->subject]),
            default => null,
        };
    }
}
