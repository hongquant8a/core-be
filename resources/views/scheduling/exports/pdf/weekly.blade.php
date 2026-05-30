<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Lịch công tác tuần {{ $week_number }} - Năm {{ $year }}</title>
    <style>
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .org-title {
            text-transform: uppercase;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 5px;
        }
        .doc-title {
            text-transform: uppercase;
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
            color: #1a4f7c;
        }
        .doc-subtitle {
            font-style: italic;
            font-size: 11px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10.5px;
        }
        th, td {
            border: 1px solid #999;
            padding: 6px 8px;
            vertical-align: middle;
        }
        th {
            background-color: #1a4f7c;
            color: white;
            font-weight: bold;
            text-align: center;
        }
        .center {
            text-align: center;
        }
        .bold {
            font-weight: bold;
        }
        tr {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="org-title">{{ $organization_name }}</div>
        <div class="doc-title">Lịch công tác tuần {{ $week_number }} - Năm {{ $year }}</div>
        @if(!empty($date_from) && !empty($date_to))
            <div class="doc-subtitle">(Từ ngày {{ $date_from }} đến ngày {{ $date_to }})</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Ngày</th>
                <th style="width: 8%;">Buổi</th>
                <th style="width: 8%;">Giờ</th>
                <th style="width: 32%;">Nội dung công tác</th>
                <th style="width: 12%;">Chủ trì</th>
                <th style="width: 12%;">Địa điểm</th>
                <th style="width: 13%;">Đơn vị chuẩn bị</th>
            </tr>
        </thead>
        <tbody>
            @if(count($schedules) === 0)
                <tr>
                    <td colspan="7" class="center">Không có lịch công tác nào trong tuần này.</td>
                </tr>
            @else
                @foreach($schedules as $item)
                    <tr>
                        @if($item['day_rowspan'] > 0)
                            <td rowspan="{{ $item['day_rowspan'] }}" class="center bold">{{ $item['day'] }}</td>
                        @endif
                        @if($item['session_rowspan'] > 0)
                            <td rowspan="{{ $item['session_rowspan'] }}" class="center">{{ $item['session'] }}</td>
                        @endif
                        <td class="center">{{ $item['time'] }}</td>
                        <td>{!! nl2br(e($item['content'])) !!}</td>
                        <td>{{ $item['host'] }}</td>
                        <td>{{ $item['location'] }}</td>
                        <td>{{ $item['prep_unit'] }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</body>
</html>
