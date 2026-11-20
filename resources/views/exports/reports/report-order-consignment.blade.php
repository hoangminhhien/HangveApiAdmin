<!DOCTYPE html>
<html lang="en">
<head>

</head>
<body>

<table id="customers">
    @foreach($data as $item)
        <tr>
            <td style="border: 1px solid #CCCCCC; width: 400px; text-align: center">
                {{ $item['data_one'] }}
            </td>
            <td style="border: 1px solid #CCCCCC; width: 400px; text-align: center">
                {{ $item['data_two'] }}
            </td>
            <td style="border: 1px solid #CCCCCC; width: 400px; text-align: center">
                {{ $item['data_three'] }}
            </td>
            <td style="border: 1px solid #CCCCCC; width: 400px; text-align: center">
                {{ $item['data_four'] }}
            </td>
            <td style="border: 1px solid #CCCCCC; width: 400px; text-align: center">
                {{ $item['data_five'] }}
            </td>
            <td style="border: 1px solid #CCCCCC; width: 400px; text-align: center">
                {{ $item['data_six'] }}
            </td>
        </tr>
    @endforeach
</table>

</body>
</html>


<?php
