<?php
/**
 * English string catalogue — the reference every other language falls back
 * to, and the key that decides what "complete" means.
 *
 * Keys are grouped by the screen a trader meets them on. A key that is
 * missing from a translation falls back to the English here rather than to a
 * blank, so a half-finished catalogue degrades to readable English instead of
 * to gaps.
 *
 * %{name} is interpolated by Lang::t().
 */

declare(strict_types=1);

return [

    /* ---------------- Common ---------------- */
    'app.name'        => 'KCCA Vendor Relocation',
    'common.language' => 'Language',
    'common.signin'    => 'Sign in',
    'common.signout'   => 'Sign out',
    'common.save'     => 'Save',
    'common.cancel'   => 'Cancel',
    'common.print'    => 'Print',
    'common.export'   => 'Export',
    'common.close'    => 'Close',
    'common.back'     => 'Back',
    'common.next'     => 'Next',
    'common.yes'      => 'Yes',
    'common.no'       => 'No',
    'common.loading'  => 'Loading…',
    'common.none'     => 'None',
    'common.all'      => 'All',
    'common.search'   => 'Search',
    'common.reset'    => 'Reset',
    'common.name'     => 'Name',
    'common.phone'    => 'Telephone',
    'common.email'    => 'Email',
    'common.date'     => 'Date',
    'common.status'   => 'Stage',
    'common.market'   => 'Market',
    'common.trade'    => 'Trade',
    'common.actions'  => 'Actions',

    /* ---------------- Sign in ---------------- */
    'login.title'    => 'Sign in',
    'login.subtitle' => 'Kampala Capital City Authority · Vendor Relocation',
    'login.username' => 'Username',
    'login.password' => 'Password',
    'login.submit'   => 'Sign in',
    'login.dark'     => 'Dark',
    'login.light'    => 'Light',
    'login.as'       => 'Sign in as',
    'login.failed'   => 'That username and password do not match. Please try again.',
    'login.expired'  => 'Your session has expired. Please try again.',
    'login.portal'   => 'Not an officer? Use the trader portal',

    /* ---------------- Portal ---------------- */
    'portal.title'    => 'Find gazetted space for your business',
    'portal.lead'     => 'Every market below is on the Authority’s register, with the space it has and what that space needs.',
    'portal.available'=> 'Space available right now',
    'portal.how'      => 'How to get a pitch',
    'portal.step1'    => 'Choose a market with space',
    'portal.step2'    => 'Pick a pitch and apply',
    'portal.step3'    => 'Bring your national ID to the division office',
    'portal.myinfo'   => 'Your details',
    'portal.contact'  => 'Contact us',
    'portal.chairman' => 'Your chairman',
    'portal.nochair'  => 'You are not yet placed in a market, so no chairman follows you yet.',
    'portal.findspace'=> 'Find a space',
    'portal.complaint'=> 'Raise a complaint',

    /* ---------------- My market ---------------- */
    'mymarket.title'      => 'My market',
    'mymarket.lead'       => 'The market you are in, the chairman responsible for you, and where your applications have got to.',
    'mymarket.chairman'   => 'Your chairman',
    'mymarket.mktitle'    => 'Your market',
    'mymarket.traders'    => 'The traders who follow you',
    'mymarket.applications'=> 'Your applications',
    'mymarket.noapps'     => 'You have not asked for a pitch yet.',
    'mymarket.pitches'    => 'Pitches in use',
    'mymarket.yourrecord' => 'My record',
    'mymarket.editme'     => 'Edit my details',
    'mymarket.pitchplan'  => 'See the pitch plan',
    'mymarket.notplaced'  => 'You are not yet in a market, so no chairman follows you yet.',
    'mymarket.entrants'   => 'Traders placed in your market yet',
    'mymarket.mytraders'  => 'Traders under you',

    /* ---------------- Attire ---------------- */
    'attire.title'    => 'Workwear for your trade',
    'attire.lead'     => 'The Authority issues workwear in public markets, and the chairman issues it in private ones.',
    'attire.yours'    => 'Prescribed for your trade',
    'attire.garment'  => 'Garment',
    'attire.colour'   => 'Colour',
    'attire.price'    => 'Price',
    'attire.issuedby' => 'Issued by',
    'attire.bykcca'   => 'KCCA',
    'attire.bychair'  => 'The market chairman',
    'attire.hold'     => 'What you have been given',
    'attire.received' => 'Received',
    'attire.outstanding' => 'Not yet confirmed',
    'attire.followup' => 'Awaiting confirmation',
    'attire.issue'    => 'Record an issue',
    'attire.none'     => 'Nothing has been issued to you yet.',

    /* ---------------- Notices ---------------- */
    'notice.title'    => 'Notices for your market',
    'notice.lead'     => 'Sent by the Authority to the chairman, who publishes them to the traders.',
    'notice.from'     => 'From the Authority',
    'notice.publish'  => 'Publish to my traders',
    'notice.published'=> 'Published',
    'notice.reply'    => 'Reply',
    'notice.none'     => 'There are no notices for you at the moment.',
    'notice.ack'      => 'Confirm you have read this',

    /* ---------------- Complaints ---------------- */
    'complaint.title'  => 'Raise a complaint',
    'complaint.lead'   => 'Your complaint goes to your chairman first. They can answer it, or pass it to the Authority.',
    'complaint.subject'=> 'Subject',
    'complaint.body'   => 'What happened',
    'complaint.send'   => 'Send to my chairman',
    'complaint.sent'   => 'Sent to your chairman.',
    'complaint.mine'   => 'My complaints',
    'complaint.status' => 'Stage',
    'complaint.withchairman' => 'With your chairman',
    'complaint.forwarded'    => 'Passed to the Authority',
    'complaint.resolved'     => 'Resolved',
    'complaint.reply'  => 'Reply from the Authority',
    'complaint.none'   => 'You have not raised a complaint.',
    'complaint.choose' => 'Choose what it is about',
    'complaint.attire'    => 'Workwear',
    'complaint.allocation'=> 'Space allocated to me',
    'complaint.fee'       => 'Fees and licences',
    'complaint.sanitation'=> 'Sanitation',
    'complaint.enforcement'=> 'Enforcement action',
    'complaint.condition' => 'Condition of the market',
];