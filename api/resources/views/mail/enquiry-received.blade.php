<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>New enquiry</title>
</head>
<body style="font-family: sans-serif; line-height: 1.5;">
    <h1 style="font-size: 18px;">New product enquiry #{{ $enquiryId }}</h1>

    <p><strong>Product:</strong> {{ $product->title }} (id {{ $product->id }}, {{ number_format($product->price, 2) }})</p>

    <table cellpadding="4">
        <tr><th align="left">Name</th><td>{{ $enquiry->name }}</td></tr>
        <tr><th align="left">Email</th><td>{{ $enquiry->email }}</td></tr>
        <tr><th align="left">Phone</th><td>{{ $enquiry->phone ?? '—' }}</td></tr>
    </table>

    <h2 style="font-size: 16px;">Message</h2>
    <p style="white-space: pre-wrap;">{{ $enquiry->message ?? '(no message)' }}</p>
</body>
</html>
