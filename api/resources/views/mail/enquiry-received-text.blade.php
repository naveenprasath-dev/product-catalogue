New product enquiry #{{ $enquiryId }}

Product: {{ $product->title }} (id {{ $product->id }}, {{ number_format($product->price, 2) }})
Name: {{ $enquiry->name }}
Email: {{ $enquiry->email }}
Phone: {{ $enquiry->phone ?? '-' }}

Message:
{{ $enquiry->message ?? '(no message)' }}
