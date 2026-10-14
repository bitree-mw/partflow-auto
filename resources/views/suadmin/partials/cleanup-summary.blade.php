<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Data</th>
                <th>Deleted</th>
                <th>Kept (still in use)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($summary['categories'] as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td><strong>{{ number_format($row['deleted']) }}</strong></td>
                    <td>{{ $row['kept'] ? number_format($row['kept']) : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<p class="suadmin-muted" style="margin: 12px 20px;">
    {{ str_replace(':rows', number_format($summary['stock']['rows']), $stockNotes[$summary['stock']['mode']]) }}
</p>
