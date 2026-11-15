<?php
/**
 * Orunyankole — Runyankole (also called Rukiga).
 *
 * DRAFT, pending review by a native speaker. Administrative text carries
 * amounts, deadlines and obligations, so a mistranslated phrase is worse than
 * an English one. Lang::coverage() reports what is still missing.
 */

declare(strict_types=1);

return [

    /* ---------------- Common ---------------- */
    'app.name'        => 'KCCA — Okusengura Ababeera',
    'common.language' => 'Orulimi',
    'common.signin'    => 'Kwingira',
    'common.signout'   => 'Kusooka',
    'common.save'     => 'Kukyega',
    'common.cancel'   => 'Kuhagya',
    'common.print'    => 'Kuprinta',
    'common.export'   => 'Kukyohereesa',
    'common.close'    => 'Kufunga',
    'common.back'     => 'Kuddayo ekyali',
    'common.next'     => 'Kukinguruka',
    'common.yes'      => 'Yee',
    'common.no'       => 'Nanga',
    'common.loading'  => 'Kikakaze okubaleka',
    'common.none'     => 'Bweri',
    'common.all'      => 'Byonna',
    'common.search'   => 'Kunoonya',
    'common.reset'    => 'Kusubiza',
    'common.name'     => 'Ebizina',
    'common.phone'    => 'Ennimukyala',
    'common.email'    => 'Email',
    'common.date'     => 'Olunaku',
    'common.status'   => 'Obumala',
    'common.market'   => 'Ssoko',
    'common.trade'    => 'Mulimu',
    'common.actions'  => 'Ebikolagala',

    /* ---------------- Sign in ---------------- */
    'login.title'    => 'Kwingira mu kina',
    'login.subtitle' => 'Kampala Capital City Authority · Okusengura Ababeera',
    'login.username' => 'Ebizina',
    'login.password' => 'Ebigyendererwa',
    'login.submit'   => 'Kwingira',
    'login.dark'     => 'Kikwateka',
    'login.light'    => 'Mulangal',
    'login.as'       => 'Kwingira nk abutali',
    'login.failed'   => 'Ebizina n\'ebigyendererwa bitakuhikirana. Buzibu.',
    'login.expired'  => 'Olukiiko lumaze okumira. Buzibu.',
    'login.portal'   => 'Sikuli mukozi wa KCCA? Koresiga Endabana ya Ababeera',

    /* ---------------- Portal ---------------- */
    'portal.title'    => 'Sooka ekizibu ekisinga obwami bw\'akyo',
    'portal.lead'     => 'Ssoko nga eri okwo okukyusa n\'ebiragikwate. Buli ssoko nga okwo okukyusa n\'ebyo ebikyali ebikwateekezwa.',
    'portal.available'=> 'Kisobola leero',
    'portal.how'      => 'Engero nga oyoke okusooka ekizibu',
    'portal.step1'    => 'Londa ssoko esingamo ekizibu',
    'portal.step2'    => 'Londa ekizibu ogyeyoomo',
    'portal.step3'    => 'Gendereza e ID yaayo ku ofiisi ya ssuku',
    'portal.myinfo'   => 'Ebikuru by\'anjala',
    'portal.contact'  => 'Tuhandikira',
    'portal.chairman' => 'Omusooka waayo',
    'portal.nochair'  => 'Tewali ssoko, kagwako omusooka waayo tawali yetu.',
    'portal.findspace'=> 'Sooka ekizibu',
    'portal.complaint'=> 'Tandika ekizibu',

    /* ---------------- My market ---------------- */
    'mymarket.title'      => 'Ssoko kange',
    'mymarket.lead'       => 'Ssoko omuri mu, omusooka asooka okutwala n\'ebikigendererwa by\'ababeera b\'akyo.',
    'mymarket.chairman'   => 'Omusooka waayo',
    'mymarket.mktitle'    => 'Ssoko kwaayo',
    'mymarket.traders'    => 'Ababeera abakorera mu ssoko',
    'mymarket.applications'=> 'Ebikigendererwa',
    'mymarket.noapps'     => 'Tewali naakusooka ekizibu.',
    'mymarket.pitches'    => 'Ebizibu ebikwattwa',
    'mymarket.yourrecord' => 'Ebikuru byange',
    'mymarket.editme'     => 'Kyikya ebikuru byange',
    'mymarket.pitchplan'  => 'Loola endiriro y\'ebizibu',
    'mymarket.notplaced'  => 'Tewali mu ssoko, kagwako omusooka waayo tawali yetu.',
    'mymarket.entrants'   => 'Ababeera abasinga mu ssoko',
    'mymarket.mytraders'  => 'Ababeera abali ku nsiko',

    /* ---------------- Attire ---------------- */
    'attire.title'    => 'Abuta ez\'okukorera ku mulimu',
    'attire.lead'     => 'Authority etwala abuta mu masami aga KCCA, n\'omusooka atwala mu masami g\'abakw-private.',
    'attire.yours'    => 'Ebikikwata ku mulimu gwaayo',
    'attire.garment'  => 'Abuta',
    'attire.colour'   => 'Olulimi lw\'ekikolo',
    'attire.price'    => 'Omusala',
    'attire.issuedby' => 'Eyakuleeta',
    'attire.bykcca'   => 'KCCA',
    'attire.bychair'  => 'Omusooka wa ssoko',
    'attire.hold'     => 'Ebikyali',
    'attire.received' => 'Ariko asaze',
    'attire.outstanding' => 'Sikalyeyo nnyo',
    'attire.followup' => 'Kikakaze okusinga obugendayo',
    'attire.issue'    => 'Wandika ekikuru',
    'attire.none'     => 'Tewali kikuru kisumulira.',

    /* ---------------- Notices ---------------- */
    'notice.title'    => 'Ebitala mu ssoko kwaayo',
    'notice.lead'     => 'Ebiva ku Authority eri omusooka, era n\'amutwala eri ababeera.',
    'notice.from'     => 'Kuva ku Authority',
    'notice.publish'  => 'Bika eri ababeera bange',
    'notice.published'=> 'Bikiddwa',
    'notice.reply'    => 'Kawukera',
    'notice.none'     => 'Tewali butale leero.',
    'notice.ack'      => 'Kikikiza nga w\'asoma',

    /* ---------------- Complaints ---------------- */
    'complaint.title'  => 'Tandika ekizibu',
    'complaint.lead'   => 'Ekizibu kyaayo kugenda eri omusooka waayo n\'okwetoolodoka. N\'amutwala eri Authority.',
    'complaint.subject'=> 'Omutwe g\'ekizibu',
    'complaint.body'   => 'Ebyaliwo',
    'complaint.send'   => 'Ohereza eri omusooka wange',
    'complaint.sent'   => 'Kohereeziwa eri omusooka waayo.',
    'complaint.mine'   => 'Ebizibu byange',
    'complaint.status' => 'Obumala',
    'complaint.withchairman' => 'Ku musooka wange',
    'complaint.forwarded'    => 'Atwala eri Authority',
    'complaint.resolved'     => 'Emalirira',
    'complaint.reply'  => 'Answeri wa Authority',
    'complaint.none'   => 'Tewali nandikira ekizibu.',
    'complaint.choose' => 'Londa ekikwata',
    'complaint.attire'    => 'Abuta ez\'okukorera',
    'complaint.allocation'=> 'Ekizibu nasinga',
    'complaint.fee'       => 'Emiigo n\'ebizina',
    'complaint.sanitation'=> 'Obunene',
    'complaint.enforcement'=> 'Ekitundu kyabano',
    'complaint.condition' => 'Obunze bw\'essoko',
];
