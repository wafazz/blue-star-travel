{{-- Renders a frozen `changes` array. Reads the stored rows only — never resolves an id
     to a label at render time, or the panel becomes one query per changed field. --}}
@if (empty($rows))
  <div class="text-secondary small">No field-level changes were recorded for this version.</div>
@else
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr><th style="width:22%">Field</th><th style="width:39%">Before</th><th style="width:39%">After</th></tr>
      </thead>
      <tbody>
        @foreach ($rows as $row)
          <tr>
            <td>
              <div class="small fw-semibold">{{ $row['label'] ?? $row['key'] }}</div>
              <div class="text-secondary" style="font-size:.72rem">{{ $row['group'] ?? '' }}</div>
            </td>
            <td class="small text-danger">
              <span @if (($row['change'] ?? '') !== 'added') style="text-decoration:line-through" @endif>{{ $row['before'] ?? '—' }}</span>
            </td>
            <td class="small fw-semibold text-success">{{ $row['after'] ?? '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@endif
