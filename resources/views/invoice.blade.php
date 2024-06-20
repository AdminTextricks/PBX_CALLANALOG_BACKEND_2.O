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

    <table>
        <tr>
            <th>Invoive </th>
            <th>Item Numbers</th>
            <th>Item Price</th>
        </tr>
        <tr>
            <td>{{ $data['invoice_number'] }}</td>
            <td>{{ $data['item'] }}</td>
            <td>{{ $data['price'] }}</td>
        </tr>
    </table>
    <h3>You can download the invoice form our portal</h3>
</body>

</html>