<tr class="date-row">
  <td><input type="date" name="dates[{{ $i }}][depart_date]" value="{{ data_get($d, 'depart_date') ? \Illuminate\Support\Str::substr(data_get($d, 'depart_date'), 0, 10) : '' }}" class="form-control form-control-sm"></td>
  <td><input type="date" name="dates[{{ $i }}][return_date]" value="{{ data_get($d, 'return_date') ? \Illuminate\Support\Str::substr(data_get($d, 'return_date'), 0, 10) : '' }}" class="form-control form-control-sm"></td>
  <td><input type="number" name="dates[{{ $i }}][seats_total]" value="{{ data_get($d, 'seats_total', 0) }}" class="form-control form-control-sm" style="width:90px"></td>
  <td><input type="number" name="dates[{{ $i }}][seats_booked]" value="{{ data_get($d, 'seats_booked', 0) }}" class="form-control form-control-sm" style="width:90px"></td>
  <td>
    <select name="dates[{{ $i }}][status]" class="form-select form-select-sm" style="width:100px">
      @foreach (['open' => 'Open', 'closed' => 'Closed', 'full' => 'Full'] as $k => $v)
        <option value="{{ $k }}" @selected(data_get($d, 'status') === $k)>{{ $v }}</option>
      @endforeach
    </select>
  </td>
  <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this, '.date-row')">🗑</button></td>
</tr>
