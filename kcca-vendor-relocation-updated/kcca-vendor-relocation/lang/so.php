<?php
/**
 * Olusoga — Lusoga.
 *
 * DRAFT, pending review by a native speaker. Administrative text carries
 * amounts, deadlines and obligations, so a mistranslated phrase is worse than
 * an English one. Lang::coverage() reports what is still missing.
 */

declare(strict_types=1);

return [

    /* ---------------- Common ---------------- */
    'app.name'        => 'KCCA — Okusengula Ababiri',
    'common.language' => 'Olulimi',
    'common.signin'    => 'Kuyinga',
    'common.signout'   => 'Kusooka',
    'common.save'     => 'Kusindika',
    'common.cancel'   => 'Kuyimiriza',
    'common.print'    => 'Kuprinta',
    'common.export'   => 'Kuyolesa',
    'common.close'    => 'Kufunga',
    'common.back'     => 'Kuddayo ekyali',
    'common.next'     => 'Kukwata',
    'common.yes'      => 'Yee',
    'common.no'       => 'Nanga',
    'common.loading'  => 'Tekalagala okubala',
    'common.none'     => 'Tewali',
    'common.all'      => 'Byonna',
    'common.search'   => 'Kunoonya',
    'common.reset'    => 'Kubiddusa',
    'common.name'     => 'Amatibu',
    'common.phone'    => 'Ennimukyala',
    'common.email'    => 'Email',
    'common.date'     => 'Olunaku',
    'common.status'   => 'Obudde',
    'common.market'   => 'Nkalindo',
    'common.trade'    => 'Mulimu',
    'common.actions'  => 'Ebintu',

    /* ---------------- Sign in ---------------- */
    'login.title'    => 'Kuyinga mu kina',
    'login.subtitle' => 'Kampala Capital City Authority · Okusengula Ababiri',
    'login.username' => 'Abuta',
    'login.password' => 'Ebigyendererwa',
    'login.submit'   => 'Kuyinga',
    'login.dark'     => 'Kikwateka',
    'login.light'    => 'Mulangal',
    'login.as'       => 'Kuyinga nga abali',
    'login.failed'   => 'Abuta n\'ebigyendererwa biri si bikuuma. Buzibu.',
    'login.expired'  => 'Olukiiko lumaze. Buzibu.',
    'login.portal'   => 'Sikuli mukozi wa KCCA? Koresiga Efula ya Ababiri',

    /* ---------------- Portal ---------------- */
    'portal.title'    => 'Nuna ekizibu ekigendererwa ku rugaba rw\'akyo',
    'portal.lead'     => 'Buli nkalindo liri ku ndandikwa y\'Authority, nga ku kifo kisobola okusengulwa n\'ebyo ebikigendererwa.',
    'portal.available'=> 'Kifo kiriwo leero',
    'portal.how'      => 'Engero nga oyole okununa ekizibu',
    'portal.step1'    => 'Londa nkalindo eririna ekizibu',
    'portal.step2'    => 'Londa ekizibu ogyeyoomo',
    'portal.step3'    => 'Gendereza e ID yaayo ku ofiisi ya ssuku',
    'portal.myinfo'   => 'Ebikuru by\'anjala',
    'portal.contact'  => 'Tuyimiriza',
    'portal.chairman' => 'Omusooka waayo',
    'portal.nochair'  => 'Tewali nkalindo, kagwako omusooka waayo tebaliwo.',
    'portal.findspace'=> 'Nuna ekizibu',
    'portal.complaint'=> 'Andika ekizibu',

    /* ---------------- My market ---------------- */
    'mymarket.title'      => 'Nkalindo lange',
    'mymarket.lead'       => 'Nkalindo omulimu, omusooka asooka okutwala n\'ebikigendererwa by\'ababiri.',
    'mymarket.chairman'   => 'Omusooka waayo',
    'mymarket.mktitle'    => 'Nkalindo kwaayo',
    'mymarket.traders'    => 'Ababiri abakorera mu nkalindo',
    'mymarket.applications'=> 'Ebikigendererwa',
    'mymarket.noapps'     => 'Tewali naakununa ekizibu.',
    'mymarket.pitches'    => 'Ebizibu ebikwattwa',
    'mymarket.yourrecord' => 'Ebikuru byange',
    'mymarket.editme'     => 'Kikyikya ebikuru byange',
    'mymarket.pitchplan'  => 'Loola enndiriro y\'ebizibu',
    'mymarket.notplaced'  => 'Tewali mu nkalindo, kagwako omusooka waayo tebaliwo.',
    'mymarket.entrants'   => 'Ababiri abasinga mu nkalindo',
    'mymarket.mytraders'  => 'Ababiri abali ku nsiko',

    /* ---------------- Attire ---------------- */
    'attire.title'    => 'Abuta ez\'okukorera ku mulimu',
    'attire.lead'     => 'Authority etwala abuta mu nkalu za gavumenti, n\'omusooka etwala mu nkalu za ssaabataka.',
    'attire.yours'    => 'Ebikikwata ku mulimu gwaayo',
    'attire.garment'  => 'Abuta',
    'attire.colour'   => 'Olulimi lw\'ekikolo',
    'attire.price'    => 'Omusala',
    'attire.issuedby' => 'Eyagula',
    'attire.bykcca'   => 'KCCA',
    'attire.bychair'  => 'Omusooka wa nkalindo',
    'attire.hold'     => 'Ebikisigiddwa',
    'attire.received' => 'Amaze okusinga',
    'attire.outstanding' => 'Sikali confirmation',
    'attire.followup' => 'Kikakaze okunyereza',
    'attire.issue'    => 'Wandika ekintu',
    'attire.none'     => 'Tewali kintu kisumulira.',

    /* ---------------- Notices ---------------- */
    'notice.title'    => 'Ebitala mu nkalindo kwaayo',
    'notice.lead'     => 'Ebifunza eby\'Authority eri omusooka, naye abibuza ababiri.',
    'notice.from'     => 'Kuva ku Authority',
    'notice.publish'  => 'Bika eri ababiri bange',
    'notice.published'=> 'Bikiddwa',
    'notice.reply'    => 'Ddayiza',
    'notice.none'     => 'Tewali butale leero.',
    'notice.ack'      => 'Kikikiza nga w\'asoma',

    /* ---------------- Complaints ---------------- */
    'complaint.title'  => 'Andika ekizibu',
    'complaint.lead'   => 'Ekizibu kyaayo kugenda eri omusooka waayo n\'okwetoolodoka. N\'amutwala eri Authority.',
    'complaint.subject'=> 'Omutwe',
    'complaint.body'   => 'Ebyaliwo',
    'complaint.send'   => 'Ohereza eri omusooka wange',
    'complaint.sent'   => 'Kohereeziwa eri omusooka waayo.',
    'complaint.mine'   => 'Ebizibu byange',
    'complaint.status' => 'Obudde',
    'complaint.withchairman' => 'Ku musooka wange',
    'complaint.forwarded'    => 'Atwala eri Authority',
    'complaint.resolved'     => 'Emalirira',
    'complaint.reply'  => 'Answeri wa Authority',
    'complaint.none'   => 'Tewali naandika ekizibu.',
    'complaint.choose' => 'Londa ekikwata',
    'complaint.attire'    => 'Abuta ez\'okukorera',
    'complaint.allocation'=> 'Ekizibu nasinga',
    'complaint.fee'       => 'Emiigo n\'ebizina',
    'complaint.sanitation'=> 'Obunene',
    'complaint.enforcement'=> 'Ekitundu kyabano',
    'complaint.condition' => 'Obunze bw\'enkalindo',
];
