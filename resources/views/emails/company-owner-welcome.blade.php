<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>NFTPayroll Hosgeldiniz</title>
</head>
<body>
    <p>Merhaba {{ $ownerName }},</p>

    <p>{{ $companyName }} sirketi icin sahip kullanici hesabin olusturuldu.</p>

    <p>Giris bilgilerin:</p>
    <ul>
        <li>E-posta: {{ $email }}</li>
        <li>Sifre: {{ $plainPassword }}</li>
    </ul>

    <p>Ilk giriste sifreni degistirmeni oneririz.</p>

    <p>NFTPayroll</p>
</body>
</html>

