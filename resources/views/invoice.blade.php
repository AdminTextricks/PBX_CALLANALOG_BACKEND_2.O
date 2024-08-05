<!DOCTYPE html>
<html>

<head>
    <title>{{ $data['title'] }}</title>
    <style>
        table {
            font-family: arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
        }

        td,
        th {
            border: 1px solid #dddddd;
            text-align: left;
            padding: 8px;
        }

        tr:nth-child(even) {
            background-color: #dddddd;
        }
    </style>
</head>

<body>
    <h1>{{ $data['title'] }}</h1>
    <h2>Invoice: {{ $data['invoice_number'] }}</h2>
    <table>
        <tr>
            <th>Item Type</th>
            @if (count($data['item_numbers']) === 1 && $data['item_types'][0] === 'Wallet Payment')
            Payment Amount
            @else
            Item Number
            @endif
        </tr>

        @foreach($data['item_numbers'] as $index => $item_number)
        <tr>
            <td>{{ $data['item_types'][$index] }}</td>
            <td>{{ $item_number }}</td>
        </tr>
        @endforeach

    </table>

    <h2>Total Price: {{ $data['price'] }}</h2>
    <h3>You can download the invoice from our portal</h3>
</body>

</html>