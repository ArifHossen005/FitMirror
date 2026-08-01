<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => ':attribute অবশ্যই গ্রহণ করতে হবে।',
    'accepted_if' => ':other :value হলে :attribute অবশ্যই গ্রহণ করতে হবে।',
    'active_url' => ':attribute অবশ্যই একটি বৈধ URL হতে হবে।',
    'after' => ':attribute অবশ্যই :date এর পরের তারিখ হতে হবে।',
    'after_or_equal' => ':attribute অবশ্যই :date তারিখ অথবা তার পরের হতে হবে।',
    'alpha' => ':attribute শুধুমাত্র অক্ষর ধারণ করতে পারবে।',
    'alpha_dash' => ':attribute শুধুমাত্র অক্ষর, সংখ্যা, ড্যাশ এবং আন্ডারস্কোর ধারণ করতে পারবে।',
    'alpha_num' => ':attribute শুধুমাত্র অক্ষর ও সংখ্যা ধারণ করতে পারবে।',
    'any_of' => ':attribute সঠিক নয়।',
    'array' => ':attribute অবশ্যই একটি অ্যারে হতে হবে।',
    'ascii' => ':attribute শুধুমাত্র সিঙ্গেল-বাইট অক্ষর ও সংখ্যা ধারণ করতে পারবে।',
    'before' => ':attribute অবশ্যই :date এর আগের তারিখ হতে হবে।',
    'before_or_equal' => ':attribute অবশ্যই :date তারিখ অথবা তার আগের হতে হবে।',
    'between' => [
        'array' => ':attribute অবশ্যই :min থেকে :max টি আইটেমের মধ্যে হতে হবে।',
        'file' => ':attribute অবশ্যই :min থেকে :max কিলোবাইটের মধ্যে হতে হবে।',
        'numeric' => ':attribute অবশ্যই :min থেকে :max এর মধ্যে হতে হবে।',
        'string' => ':attribute অবশ্যই :min থেকে :max অক্ষরের মধ্যে হতে হবে।',
    ],
    'boolean' => ':attribute অবশ্যই true অথবা false হতে হবে।',
    'can' => ':attribute একটি অননুমোদিত মান ধারণ করছে।',
    'confirmed' => ':attribute নিশ্চিতকরণ মিলছে না।',
    'contains' => ':attribute এ একটি প্রয়োজনীয় মান অনুপস্থিত।',
    'current_password' => 'পাসওয়ার্ডটি সঠিক নয়।',
    'date' => ':attribute অবশ্যই একটি বৈধ তারিখ হতে হবে।',
    'date_equals' => ':attribute অবশ্যই :date তারিখের সমান হতে হবে।',
    'date_format' => ':attribute অবশ্যই :format ফরম্যাটের সাথে মিলতে হবে।',
    'decimal' => ':attribute অবশ্যই :decimal দশমিক স্থান থাকতে হবে।',
    'declined' => ':attribute অবশ্যই প্রত্যাখ্যান করতে হবে।',
    'declined_if' => ':other :value হলে :attribute অবশ্যই প্রত্যাখ্যান করতে হবে।',
    'different' => ':attribute এবং :other অবশ্যই ভিন্ন হতে হবে।',
    'digits' => ':attribute অবশ্যই :digits সংখ্যার হতে হবে।',
    'digits_between' => ':attribute অবশ্যই :min থেকে :max সংখ্যার মধ্যে হতে হবে।',
    'dimensions' => ':attribute এর ছবির মাপ সঠিক নয়।',
    'distinct' => ':attribute এ একটি ডুপ্লিকেট মান রয়েছে।',
    'doesnt_contain' => ':attribute এর মধ্যে নিম্নলিখিত কোনোটি থাকা যাবে না: :values.',
    'doesnt_end_with' => ':attribute নিম্নলিখিত কোনোটি দিয়ে শেষ হতে পারবে না: :values.',
    'doesnt_start_with' => ':attribute নিম্নলিখিত কোনোটি দিয়ে শুরু হতে পারবে না: :values.',
    'email' => ':attribute অবশ্যই একটি বৈধ ইমেইল ঠিকানা হতে হবে।',
    'encoding' => ':attribute অবশ্যই :encoding এ এনকোড করা থাকতে হবে।',
    'ends_with' => ':attribute অবশ্যই নিম্নলিখিত কোনো একটি দিয়ে শেষ হতে হবে: :values.',
    'enum' => 'নির্বাচিত :attribute সঠিক নয়।',
    'exists' => 'নির্বাচিত :attribute সঠিক নয়।',
    'extensions' => ':attribute অবশ্যই নিম্নলিখিত এক্সটেনশনের একটি হতে হবে: :values.',
    'file' => ':attribute অবশ্যই একটি ফাইল হতে হবে।',
    'filled' => ':attribute এ অবশ্যই একটি মান থাকতে হবে।',
    'gt' => [
        'array' => ':attribute এ অবশ্যই :value টির বেশি আইটেম থাকতে হবে।',
        'file' => ':attribute অবশ্যই :value কিলোবাইটের চেয়ে বড় হতে হবে।',
        'numeric' => ':attribute অবশ্যই :value এর চেয়ে বড় হতে হবে।',
        'string' => ':attribute অবশ্যই :value অক্ষরের চেয়ে বড় হতে হবে।',
    ],
    'gte' => [
        'array' => ':attribute এ অবশ্যই :value টি অথবা তার বেশি আইটেম থাকতে হবে।',
        'file' => ':attribute অবশ্যই :value কিলোবাইট অথবা তার বেশি হতে হবে।',
        'numeric' => ':attribute অবশ্যই :value অথবা তার বেশি হতে হবে।',
        'string' => ':attribute অবশ্যই :value অক্ষর অথবা তার বেশি হতে হবে।',
    ],
    'hex_color' => ':attribute অবশ্যই একটি বৈধ হেক্সাডেসিমেল রঙ হতে হবে।',
    'image' => ':attribute অবশ্যই একটি ছবি হতে হবে।',
    'in' => 'নির্বাচিত :attribute সঠিক নয়।',
    'in_array' => ':attribute অবশ্যই :other এ বিদ্যমান থাকতে হবে।',
    'in_array_keys' => ':attribute এ অন্তত নিম্নলিখিত একটি কী থাকতে হবে: :values.',
    'integer' => ':attribute অবশ্যই একটি পূর্ণসংখ্যা হতে হবে।',
    'ip' => ':attribute অবশ্যই একটি বৈধ IP ঠিকানা হতে হবে।',
    'ipv4' => ':attribute অবশ্যই একটি বৈধ IPv4 ঠিকানা হতে হবে।',
    'ipv6' => ':attribute অবশ্যই একটি বৈধ IPv6 ঠিকানা হতে হবে।',
    'json' => ':attribute অবশ্যই একটি বৈধ JSON স্ট্রিং হতে হবে।',
    'list' => ':attribute অবশ্যই একটি তালিকা হতে হবে।',
    'lowercase' => ':attribute অবশ্যই ছোট হাতের অক্ষরে হতে হবে।',
    'lt' => [
        'array' => ':attribute এ অবশ্যই :value টির কম আইটেম থাকতে হবে।',
        'file' => ':attribute অবশ্যই :value কিলোবাইটের চেয়ে ছোট হতে হবে।',
        'numeric' => ':attribute অবশ্যই :value এর চেয়ে ছোট হতে হবে।',
        'string' => ':attribute অবশ্যই :value অক্ষরের চেয়ে ছোট হতে হবে।',
    ],
    'lte' => [
        'array' => ':attribute এ :value টির বেশি আইটেম থাকতে পারবে না।',
        'file' => ':attribute অবশ্যই :value কিলোবাইট অথবা তার কম হতে হবে।',
        'numeric' => ':attribute অবশ্যই :value অথবা তার কম হতে হবে।',
        'string' => ':attribute অবশ্যই :value অক্ষর অথবা তার কম হতে হবে।',
    ],
    'mac_address' => ':attribute অবশ্যই একটি বৈধ MAC ঠিকানা হতে হবে।',
    'max' => [
        'array' => ':attribute এ :max টির বেশি আইটেম থাকতে পারবে না।',
        'file' => ':attribute অবশ্যই :max কিলোবাইটের চেয়ে বড় হতে পারবে না।',
        'numeric' => ':attribute অবশ্যই :max এর চেয়ে বড় হতে পারবে না।',
        'string' => ':attribute অবশ্যই :max অক্ষরের চেয়ে বড় হতে পারবে না।',
    ],
    'max_digits' => ':attribute এ :max অঙ্কের বেশি থাকতে পারবে না।',
    'mimes' => ':attribute অবশ্যই এই ধরনের ফাইল হতে হবে: :values.',
    'mimetypes' => ':attribute অবশ্যই এই ধরনের ফাইল হতে হবে: :values.',
    'min' => [
        'array' => ':attribute এ অন্তত :min টি আইটেম থাকতে হবে।',
        'file' => ':attribute অবশ্যই অন্তত :min কিলোবাইট হতে হবে।',
        'numeric' => ':attribute অবশ্যই অন্তত :min হতে হবে।',
        'string' => ':attribute অবশ্যই অন্তত :min অক্ষর হতে হবে।',
    ],
    'min_digits' => ':attribute এ অন্তত :min অঙ্ক থাকতে হবে।',
    'missing' => ':attribute অবশ্যই অনুপস্থিত থাকতে হবে।',
    'missing_if' => ':other :value হলে :attribute অবশ্যই অনুপস্থিত থাকতে হবে।',
    'missing_unless' => ':other :value না হলে :attribute অবশ্যই অনুপস্থিত থাকতে হবে।',
    'missing_with' => ':values উপস্থিত থাকলে :attribute অবশ্যই অনুপস্থিত থাকতে হবে।',
    'missing_with_all' => ':values সবগুলো উপস্থিত থাকলে :attribute অবশ্যই অনুপস্থিত থাকতে হবে।',
    'multiple_of' => ':attribute অবশ্যই :value এর গুণিতক হতে হবে।',
    'not_in' => 'নির্বাচিত :attribute সঠিক নয়।',
    'not_regex' => ':attribute এর ফরম্যাট সঠিক নয়।',
    'numeric' => ':attribute অবশ্যই একটি সংখ্যা হতে হবে।',
    'password' => [
        'letters' => ':attribute এ অন্তত একটি অক্ষর থাকতে হবে।',
        'mixed' => ':attribute এ অন্তত একটি বড় হাতের এবং একটি ছোট হাতের অক্ষর থাকতে হবে।',
        'numbers' => ':attribute এ অন্তত একটি সংখ্যা থাকতে হবে।',
        'symbols' => ':attribute এ অন্তত একটি চিহ্ন থাকতে হবে।',
        'uncompromised' => 'এই :attribute একটি ডেটা লিকে পাওয়া গেছে। অনুগ্রহ করে ভিন্ন একটি :attribute বেছে নিন।',
    ],
    'present' => ':attribute অবশ্যই উপস্থিত থাকতে হবে।',
    'present_if' => ':other :value হলে :attribute অবশ্যই উপস্থিত থাকতে হবে।',
    'present_unless' => ':other :value না হলে :attribute অবশ্যই উপস্থিত থাকতে হবে।',
    'present_with' => ':values উপস্থিত থাকলে :attribute অবশ্যই উপস্থিত থাকতে হবে।',
    'present_with_all' => ':values সবগুলো উপস্থিত থাকলে :attribute অবশ্যই উপস্থিত থাকতে হবে।',
    'prohibited' => ':attribute নিষিদ্ধ।',
    'prohibited_if' => ':other :value হলে :attribute নিষিদ্ধ।',
    'prohibited_if_accepted' => ':other গৃহীত হলে :attribute নিষিদ্ধ।',
    'prohibited_if_declined' => ':other প্রত্যাখ্যাত হলে :attribute নিষিদ্ধ।',
    'prohibited_unless' => ':other, :values এর মধ্যে না থাকলে :attribute নিষিদ্ধ।',
    'prohibits' => ':attribute থাকলে :other উপস্থিত থাকতে পারবে না।',
    'regex' => ':attribute এর ফরম্যাট সঠিক নয়।',
    'required' => ':attribute আবশ্যক।',
    'required_array_keys' => ':attribute এ অবশ্যই নিম্নলিখিত এন্ট্রিগুলো থাকতে হবে: :values.',
    'required_if' => ':other :value হলে :attribute আবশ্যক।',
    'required_if_accepted' => ':other গৃহীত হলে :attribute আবশ্যক।',
    'required_if_declined' => ':other প্রত্যাখ্যাত হলে :attribute আবশ্যক।',
    'required_unless' => ':other, :values এর মধ্যে না থাকলে :attribute আবশ্যক।',
    'required_with' => ':values উপস্থিত থাকলে :attribute আবশ্যক।',
    'required_with_all' => ':values সবগুলো উপস্থিত থাকলে :attribute আবশ্যক।',
    'required_without' => ':values উপস্থিত না থাকলে :attribute আবশ্যক।',
    'required_without_all' => ':values এর কোনোটিই উপস্থিত না থাকলে :attribute আবশ্যক।',
    'same' => ':attribute অবশ্যই :other এর সাথে মিলতে হবে।',
    'size' => [
        'array' => ':attribute এ অবশ্যই :size টি আইটেম থাকতে হবে।',
        'file' => ':attribute অবশ্যই :size কিলোবাইট হতে হবে।',
        'numeric' => ':attribute অবশ্যই :size হতে হবে।',
        'string' => ':attribute অবশ্যই :size অক্ষর হতে হবে।',
    ],
    'starts_with' => ':attribute অবশ্যই নিম্নলিখিত কোনো একটি দিয়ে শুরু হতে হবে: :values.',
    'string' => ':attribute অবশ্যই একটি স্ট্রিং হতে হবে।',
    'timezone' => ':attribute অবশ্যই একটি বৈধ টাইমজোন হতে হবে।',
    'unique' => 'এই :attribute ইতিমধ্যে ব্যবহৃত হয়েছে।',
    'uploaded' => ':attribute আপলোড করতে ব্যর্থ হয়েছে।',
    'uppercase' => ':attribute অবশ্যই বড় হাতের অক্ষরে হতে হবে।',
    'url' => ':attribute অবশ্যই একটি বৈধ URL হতে হবে।',
    'ulid' => ':attribute অবশ্যই একটি বৈধ ULID হতে হবে।',
    'uuid' => ':attribute অবশ্যই একটি বৈধ UUID হতে হবে।',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [],

];
