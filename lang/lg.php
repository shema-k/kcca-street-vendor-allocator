<?php
/**
 * Oluganda — Luganda.
 *
 * DRAFT, pending review by a native speaker. The keys and the fallback are
 * final; the wording is not signed off. Administrative text carries amounts,
 * deadlines and obligations, so a mistranslated phrase is worse than an
 * English one. Lang::coverage() reports what is still missing.
 */

declare(strict_types=1);

return [

    /* ---------------- Common ---------------- */
    'app.name'        => 'KCCA — Okusengulwa Abavanni',
    'common.language' => 'Olulimi',
    'common.signin'    => 'Kuyooloka',
    'common.signout'   => 'Okusooka',
    'common.save'     => 'Okubika',
    'common.cancel'   => 'Okuhikiza',
    'common.print'    => 'Okuprinta',
    'common.export'   => 'Okukootesa',
    'common.close'    => 'Okufunga',
    'common.back'     => 'Okuddayo eizibu',
    'common.next'     => 'Okweyongera',
    'common.yes'      => 'Nnoola',
    'common.no'       => 'Nagaali',
    'common.loading'  => 'Kali kukubaluka',
    'common.none'     => 'Tewali',
    'common.all'      => 'Byonna',
    'common.search'   => 'Kusooka',
    'common.reset'    => 'Kukomya',
    'common.name'     => 'Amalinnya',
    'common.phone'    => 'Ennimi',
    'common.email'    => 'Email',
    'common.date'     => 'Olunaku',
    'common.status'   => 'Obudde',
    'common.market'   => 'Kikubo',
    'common.trade'    => 'Mulimu',
    'common.actions'  => 'Emirimu',

    /* ---------------- Sign in ---------------- */
    'login.title'    => 'Kuyooloka mu kina',
    'login.subtitle' => 'Kampala Capital City Authority · Okusengulwa Abavanni',
    'login.username' => 'Abuta',
    'login.password' => 'Ekikwazo',
    'login.submit'   => 'Kuyooloka',
    'login.dark'     => 'Ndark',
    'login.light'    => 'Amakeera',
    'login.as'       => 'Kuyooloka nga abali',
    'login.failed'   => 'Abuta n\'ekikwazo biri si bimaze okusingana. Bikyalamu.',
    'login.expired'  => 'Oluzeeko lwagulibwa. Bikyalamu.',
    'login.portal'   => 'Si mukozi wa KCCA? Kkozesa Endabana y\'abavanni',

    /* ---------------- Portal ---------------- */
    'portal.title'    => 'Nuna ekizibu ekikwateekezeddwa ku rugaba rw\'akyo',
    'portal.lead'     => 'Bikubo byonna wansi biri ku ndandikwa y\'Authority, nga ku kifo kisobola okukyusa n\'ebyo ebikyali by\'ekigendererwa.',
    'portal.available'=> 'Kifo kiriwo leero',
    'portal.how'      => 'Engero okunyola ekizibu',
    'portal.step1'    => 'Londa kikubo ekirina kifo',
    'portal.step2'    => 'Londa ekizibu era ogyeyoomo',
    'portal.step3'    => 'Gendereza e ID yaayo ku ofiisi eggaali y\'obunaku bwa ssuku',
    'portal.myinfo'   => 'Amakuru g\'anjala',
    'portal.contact'  => 'Tukgana',
    'portal.chairman' => 'Omukasa waayo',
    'portal.nochair'  => 'Tewali kikubo, nga kino tebaakwanga omukasa waayo.',
    'portal.findspace'=> 'Nuna ekizibu',
    'portal.complaint'=> 'Salawo ekizibu',

    /* ---------------- My market ---------------- */
    'mymarket.title'      => 'Kikubo kange',
    'mymarket.lead'       => 'Kikubo kikiri mu, omukasa asankwa ku kukwasenya, n\'ebikwatagana ebyakiba byaayo.',
    'mymarket.chairman'   => 'Omukasa waayo',
    'mymarket.mktitle'    => 'Kikubo kwaayo',
    'mymarket.traders'    => 'Abavanni abakkolela ku nsi',
    'mymarket.applications'=> 'Ebikwata byaayo',
    'mymarket.noapps'     => 'Tewali naakuwa ekizibu.',
    'mymarket.pitches'    => 'Ekinzu ebikwatta',
    'mymarket.yourrecord' => 'Amanduku ange',
    'mymarket.editme'     => 'Kyikya amanduku ange',
    'mymarket.pitchplan'  => 'Loola endiriro y\'ebizibu',
    'mymarket.notplaced'  => 'Tewali mu kikubo, nga kino tebaakwanga omukasa waayo.',
    'mymarket.entrants'   => 'Abavanni abaakatisizza mu kikubo',
    'mymarket.mytraders'  => 'Abavanni abali ku nsiko',

    /* ---------------- Attire ---------------- */
    'attire.title'    => 'Abuta ez\'obutikira ku mulimu',
    'attire.lead'     => 'Authority etaanga ezobutikira mu bibuzo bya gavumenti, n\'omukasa etaanga mu bibuzo bya ssaabataka.',
    'attire.yours'    => 'Ebikwata ku mulimu gwaayo',
    'attire.garment'  => 'Abuta',
    'attire.colour'   => 'Lukalu',
    'attire.price'    => 'Mutundulo',
    'attire.issuedby' => 'Awa',
    'attire.bykcca'   => 'KCCA',
    'attire.bychair'  => 'Omukasa wa kikubo',
    'attire.hold'     => 'Ebikwatagana',
    'attire.received' => 'Amaze okufuna',
    'attire.outstanding' => 'Terate nawaaka',
    'attire.followup' => 'Tekisobola okukikonyoza',
    'attire.issue'    => 'Wandika ekiroobiiro',
    'attire.none'     => 'Tewali kiroobiiro kirikusiddwa ku kikubo.',

    /* ---------------- Notices ---------------- */
    'notice.title'    => 'Ebitala mu kikubo kwaayo',
    'notice.lead'     => 'Ebifunza eby\'Authority eri omukasa, naye abibuza abavanni.',
    'notice.from'     => 'Kuva ku Authority',
    'notice.publish'  => 'Bika ku bavanni bange',
    'notice.published'=> 'Bikiddwa',
    'notice.reply'    => 'Ddayiza',
    'notice.none'     => 'Tewali buli eitala.',
    'notice.ack'      => 'Kikita nga wandiiide',

    /* ---------------- Complaints ---------------- */
    'complaint.title'  => 'Salawo ekizibu',
    'complaint.lead'   => 'Ekizibu kyaayo kugenda ku mukasa waayo ekryokero. Naye asobola kulwoko, n\'akakiddwa eri Authority.',
    'complaint.subject'=> 'Ensiba',
    'complaint.body'   => 'Ebyaliwo',
    'complaint.send'   => 'Kweeriza ku mukasa wange',
    'complaint.sent'   => 'Kyeeriziddwa ku mukasa waayo.',
    'complaint.mine'   => 'Ebizibu byange',
    'complaint.status' => 'Obudde',
    'complaint.withchairman' => 'Ku mukasa wange',
    'complaint.forwarded'    => 'Yakolekeza eri Authority',
    'complaint.resolved'     => 'Emaliriridwa',
    'complaint.reply'  => 'Answeri wa Authority',
    'complaint.none'   => 'Tewali naasalawo ekizibu.',
    'complaint.choose' => 'Londa ekikwata',
    'complaint.attire'    => 'Abuta ez\'obutikira',
    'complaint.allocation'=> 'Ekizibu ekinyiddwa',
    'complaint.fee'       => 'Amaganda n\'obujjo',
    'complaint.sanitation'=> 'Obunene',
    'complaint.enforcement'=> 'Enkizibu y\'obujjo',
    'complaint.condition' => 'Obunze bw\'ekikubo',
];