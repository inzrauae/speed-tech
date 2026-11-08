<?php

$repairServices = [
    [
        'slug' => 'laptop-pc-repair',
        'name' => 'Laptop & PC repairs',
        'icon' => 'laptop',
        'summary' => 'Screens, keyboards, hinges, charging ports, overheating and power faults on laptops and desktops.',
        'description' => 'Most laptop and desktop faults come down to a worn or damaged part: a cracked screen, a failing keyboard, a loose charging port or a fan clogged with dust. We find the actual cause, show you what needs replacing and fit quality parts, often from our own spare parts stock, so your device is back in use quickly.',
        'signs' => ['Cracked, dark or flickering screen', 'Keys or touchpad not responding', 'Laptop not charging or charging only at an angle', 'Loud fan, overheating or sudden shutdowns', 'Broken hinge or lid that will not stay open', 'No power or no display'],
        'includes' => ['Broken or flickering screen replacement', 'Keyboard, touchpad and hinge repair', 'DC jack and charging faults', 'Fan cleaning, thermal paste and overheating fixes', 'No power / no display diagnosis'],
        'turnaround' => 'Same or next day for most jobs',
    ],
    [
        'slug' => 'motherboard-repair',
        'name' => 'Motherboard repair',
        'icon' => 'cpu',
        'summary' => 'Board-level diagnosis and repair, so you do not always have to buy a new motherboard.',
        'description' => 'A motherboard fault does not always mean an expensive replacement. We trace power and signal faults on the board, clean and repair liquid damage, and replace failed components at chip level. If a repair is not worth it, we tell you honestly and source a replacement board for your exact model.',
        'signs' => ['Device completely dead after a spill', 'Powers on but no display', 'Restarts or shuts down at random', 'Charging light on but nothing happens', 'Burning smell or visible damage on the board', 'USB, audio or Wi-Fi ports stopped working'],
        'includes' => ['Liquid damage cleaning and repair', 'Power rail and short-circuit tracing', 'Chip-level component replacement', 'BIOS faults and recovery', 'Motherboard replacement if repair is not viable'],
        'turnaround' => '2 to 5 working days',
    ],
    [
        'slug' => 'ram-ssd-upgrades',
        'name' => 'RAM & SSD upgrades',
        'icon' => 'ram',
        'summary' => 'Make an older laptop or PC feel new with more memory and a fast solid state drive.',
        'description' => 'An SSD and extra memory are the most cost-effective way to speed up a slow computer. We check exactly which memory and drives your model supports, install and test the upgrade, and move your Windows, programs and files across so everything works as before, only faster.',
        'signs' => ['Takes minutes to start up', 'Freezes with several tabs or programs open', 'Disk usage stuck at 100%', 'Not enough storage space', 'Old hard drive making clicking noises', 'You want more life from your current laptop'],
        'includes' => ['Compatibility check for your exact model', 'RAM installation and testing', 'HDD to SSD upgrade', 'Copying your existing system and files', 'Windows reinstall on request'],
        'turnaround' => 'Usually within the same day',
    ],
    [
        'slug' => 'data-recovery',
        'name' => 'Data recovery',
        'icon' => 'storage',
        'summary' => 'Recover photos, documents and files from failed, deleted or damaged drives.',
        'description' => 'Lost files are stressful, but they can often be recovered. We assess the drive, work on a copy wherever possible to avoid further damage, and recover photos, documents and business files to a new drive. Your data is handled confidentially from start to finish.',
        'signs' => ['Drive not detected or asking to be formatted', 'Files deleted by mistake', 'Laptop will not boot but you need the files', 'Clicking or beeping hard drive', 'Memory card or USB drive shows as empty', 'Data loss after a virus or failed update'],
        'includes' => ['Hard drive, SSD, USB and memory card recovery', 'Accidentally deleted files', 'Corrupted or unbootable systems', 'Backup to a new drive', 'Confidential handling of your data'],
        'turnaround' => '1 to 5 working days, depending on the drive',
    ],
    [
        'slug' => 'virus-removal',
        'name' => 'Virus removal',
        'icon' => 'shield',
        'summary' => 'Remove viruses, malware and pop-ups, then protect your device so it stays clean.',
        'description' => 'Malware slows your computer, shows unwanted pop-ups and can steal passwords. We scan and clean the system thoroughly, remove unwanted programs and browser hijackers, update Windows and set up reliable protection, then tune start-up so the computer runs smoothly again.',
        'signs' => ['Pop-ups and unwanted adverts', 'Browser home page or search changed by itself', 'Computer suddenly very slow', 'Programs you did not install', 'Antivirus disabled or warnings appearing', 'Friends receiving strange messages from your account'],
        'includes' => ['Full malware and virus scan', 'Removal of adware and unwanted programs', 'Browser clean-up', 'Security software setup', 'Speed and start-up optimisation'],
        'turnaround' => 'Same or next day',
    ],
    [
        'slug' => 'pc-assembly',
        'name' => 'PC assembly',
        'icon' => 'box',
        'summary' => 'Custom desktop builds for work, study, design and gaming, matched to your budget.',
        'description' => 'Tell us how you will use the PC and your budget, and we will recommend compatible parts for work, study, design or gaming. We assemble it neatly, install Windows and drivers, and stress-test the system before handover. We can also upgrade the PC you already have.',
        'signs' => ['Planning a new gaming or work PC', 'Unsure which parts are compatible', 'Your current PC cannot run new software', 'You want a quiet, tidy, reliable build', 'Upgrading graphics card, CPU or power supply', 'Building PCs for an office'],
        'includes' => ['Parts advice and compatibility check', 'Professional assembly and cable management', 'Windows and driver installation', 'Stress testing before handover', 'Upgrades to your existing PC'],
        'turnaround' => '1 to 3 days once parts are ready',
    ],
    [
        'slug' => 'networking',
        'name' => 'Networking solutions',
        'icon' => 'wifi',
        'summary' => 'Reliable Wi-Fi and wired networks for homes, offices and small businesses.',
        'description' => 'Reliable internet matters at home and at work. We set up and secure routers and Wi-Fi, fix weak signal areas, run network cabling for offices and get printers and shared folders working across your devices. On-site visits are arranged by appointment.',
        'signs' => ['Wi-Fi drops or is weak in some rooms', 'Slow internet on some devices', 'Setting up a new office or shop', 'Printer not working across the network', 'Need secure guest Wi-Fi', 'CCTV or network devices offline'],
        'includes' => ['Router and Wi-Fi setup', 'Weak signal and dead-zone fixes', 'Office network cabling', 'Printer and file sharing', 'CCTV and network troubleshooting'],
        'turnaround' => 'On-site visits by appointment',
    ],
    [
        'slug' => 'software-it-support',
        'name' => 'Software & IT support',
        'icon' => 'headphones',
        'summary' => 'Windows installs, software setup and friendly help when your tech does not behave.',
        'description' => 'From a fresh Windows installation to setting up email, Office and printers, we handle the software side so you can get on with your work. Small businesses can rely on us for ongoing, friendly IT support when something stops working.',
        'signs' => ['Windows errors or failed updates', 'Need Windows reinstalled', 'Software or drivers will not install', 'Email or Office not set up', 'Computer slow for no clear reason', 'Small business needing regular IT help'],
        'includes' => ['Windows installation and updates', 'Driver and software installation', 'Email and Office setup', 'Slow computer tune-up', 'Ongoing support for small businesses'],
        'turnaround' => 'Same day for most jobs',
    ],
];

return [
    'name' => env('APP_NAME', 'SpeedTech IT Solutions'),
    'phone' => env('SHOP_PHONE', '+94750591279'),
    'phone_display' => env('SHOP_PHONE_DISPLAY', '075 059 1279'),
    'whatsapp' => env('SHOP_WHATSAPP', '94750591279'),
    'email' => env('SHOP_EMAIL', 'info@speedtech.lk'),
    'address' => env('SHOP_ADDRESS', '300/2/1 Samagi Mawatha, Kaduwela 10640, Sri Lanka'),
    'maps_url' => env('SHOP_MAPS_URL', 'https://maps.app.goo.gl/vfGEvh1T6Yns4QHy7'),
    'reviews_url' => env('SHOP_REVIEWS_URL', 'https://share.google/zRSkab6cI6Dv31Oqn'),
    'facebook_url' => env('SHOP_FACEBOOK_URL', 'https://www.facebook.com/Speedtechitsolution'),
    // Google Maps embed for the contact page (Maps > Share > Embed a map gives an exact URL).
    'maps_embed' => env('SHOP_MAPS_EMBED', 'https://maps.google.com/maps?q='.rawurlencode('SpeedTech IT Solutions, 300/2/1 Samagi Mawatha, Kaduwela').'&z=16&output=embed'),

    // Orders
    'currency' => env('SHOP_CURRENCY', 'Rs.'),
    'delivery_fee' => (float) env('SHOP_DELIVERY_FEE', 450),
    'free_delivery_over' => (float) env('SHOP_FREE_DELIVERY_OVER', 25000),
    'max_line_quantity' => 10,
    'bank_details' => env('SHOP_BANK_DETAILS', 'Bank details will be shared by our team when we confirm your order.'),

    // Where order / enquiry notification e-mails are sent. Leave empty to disable.
    'notify_email' => env('SHOP_NOTIFY_EMAIL'),

    // First admin account (used by AdminUserSeeder).
    'admin_email' => env('ADMIN_EMAIL', 'admin@speedtech.lk'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // One-time browser installer at /install/{token}. Set a long random value to
    // enable it on a host without terminal access, then REMOVE it after installing.
    'install_token' => env('INSTALL_TOKEN'),

    // Service names used by the booking form (kept in sync with repair_services below).
    'services' => array_column($repairServices, 'name'),

    // Shown on the /repairs page. Edit freely: turnaround times are typical
    // estimates and every job is quoted after inspection.
    'repair_services' => $repairServices,

    'repair_process' => [
        ['Book or drop in', 'Book online, message us on WhatsApp or bring your device to our Kaduwela store.'],
        ['Inspection & quote', 'We diagnose the fault and explain the problem, the options and the price before any work starts.'],
        ['Repair with care', 'Your approved repair is carried out with quality parts, and we test everything before handing it back.'],
        ['Collect & warranty', 'Pick up your device or arrange delivery. Replaced parts are covered by a parts warranty.'],
    ],

    // Home page: practical laptop care tips. Link with 'route' => [name, params], or 'part' => part-type slug.
    'care_tips' => [
        ['icon' => 'bolt', 'title' => 'Look after your battery', 'text' => 'Avoid leaving your laptop at 100% on the charger all day, and keep it out of hot cars. If the battery swells, drains in minutes or stops charging, replace it. A swollen battery can damage the trackpad and casing.', 'cta' => 'Shop laptop batteries', 'part' => 'battery'],
        ['icon' => 'cpu', 'title' => 'Keep it cool', 'text' => 'Use your laptop on a hard, flat surface, not a bed or cushion. A loud fan, hot keyboard or sudden shutdowns usually mean dust in the fan and old thermal paste. A clean-out restores performance and protects the motherboard.', 'cta' => 'Book a cleaning', 'route' => ['repairs.show', ['service' => 'laptop-pc-repair']]],
        ['icon' => 'storage', 'title' => 'Back up before it fails', 'text' => 'Keep important files in two places: an external drive and a cloud service. Clicking sounds, slow file opening or disk errors are early warnings. Back up straight away and bring the drive to us.', 'cta' => 'Data recovery help', 'route' => ['repairs.show', ['service' => 'data-recovery']]],
        ['icon' => 'ram', 'title' => 'Upgrade instead of replacing', 'text' => 'A slow laptop often only needs an SSD and more RAM, for far less than a new machine. Tell us your model and we will confirm which memory and drives it supports.', 'cta' => 'See RAM & SSD upgrades', 'route' => ['repairs.show', ['service' => 'ram-ssd-upgrades']]],
    ],

    // Home page: shopping questions.
    'shop_faqs' => [
        ['How do I find the right spare part?', 'Use the finder to choose your laptop brand, model and year, then the part type. Parts can differ within the same model, so check the part number printed on your old part or laptop label. Not sure? Send us a part request or a photo on WhatsApp and we will confirm it before you buy.'],
        ['Do you deliver?', 'Yes, we deliver island-wide. :delivery_text You can also collect free of charge from our Kaduwela store.'],
        ['How can I pay?', 'Choose cash on delivery or bank transfer at checkout, or pay at the store when you collect. For bank transfers you can upload the payment slip after ordering. We call you to confirm every order before it is dispatched.'],
        ['Can you fit the part for me?', 'Yes. Order the part and mention fitting in your order notes, or bring your laptop to our store. We will fit and test it for you.'],
        ['What if an item shows "Price on request"?', 'Prices for some items change with supplier stock. Tap "Ask for price" and we will reply with the current price and availability, usually the same day.'],
    ],

    'repair_faqs' => [
        ['How long will my repair take?', 'Many common repairs, such as screens, keyboards, batteries and upgrades, are done the same or next working day when the part is in stock. Board-level repairs and parts we need to order can take longer. We give you a time estimate with your quote.'],
        ['Will I know the price before you start?', 'Yes. We inspect the device first and give you a quote. No repair work starts until you approve it.'],
        ['Is my data safe?', 'We treat every device as private. For repairs that touch storage we recommend a backup first, and we can do the backup for you.'],
        ['Do you give a warranty?', 'Yes. Replacement parts carry a parts warranty, typically 3 to 6 months depending on the part. The warranty period is written on your bill.'],
        ['Which brands do you repair?', 'Dell, HP, Lenovo, ASUS, Acer, MSI, Apple, Toshiba, Samsung and most other laptop and desktop brands.'],
        ['Can I buy a part and have you fit it?', 'Yes. Find the part in our spare parts section and ask for fitting when you order, or bring your laptop in and we will source and fit it.'],
    ],
];
