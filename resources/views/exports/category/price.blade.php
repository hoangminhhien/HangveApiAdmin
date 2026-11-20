<html>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<table>
    <thead>
    <tr>
        <th style="text-align:center;">1</th>
        <th style="text-align:center;">2</th>
        <th style="text-align:center;">3</th>
        <th style="text-align:center;">4</th>
        <th style="text-align:center;">5</th>
    </tr>
    <tr>
        <th rowspan="2" style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle; font-weight: bold; background-color: #B0CE94;">Danh mục</th>
        <th colspan="2" style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle; font-weight: bold;background-color: #B0CE94;">Hà Nội</th>
        <th colspan="2" style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle; font-weight: bold;background-color: #B0CE94;">Hồ Chí Minh</th>
    </tr>
    <tr>
        <th style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle; font-weight: bold;background-color: #B0C6A0;" width="20">KG</th>
        <th style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle; font-weight: bold;background-color: #B0C6A0;" width="20">M3</th>
        <th style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle; font-weight: bold;background-color: #B0C6A0;" width="20">KG</th>
        <th style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle; font-weight: bold;background-color: #B0C6A0;" width="20">M3</th>
    </tr>
    </thead>
    <tbody>
        @foreach($categoryDBs as $key => $category)
        <tr>
            <th style="border:1px solid #333;height:25px;background-color: #B0C6A0; padding-left: 5px"> {{ $category->name }}</th>
            <th style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">{{ $category->hn_kg }}</th>
            <th style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">{{ $category->hn_m3 }}</th>
            <th style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">{{ $category->hcm_kg }}</th>
            <th style="text-align:center; border:1px solid #333;height:25px;vertical-align: middle;">{{ $category->hcm_m3 }}</th>
        </tr>
        @endforeach
    </tbody>
</table>
</html>
