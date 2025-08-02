const questions = [
  {
    id: 1,
    question: "Iyo ugeze ku cyapa kiriho amagambo 'STOP', ugomba gukora iki?",
    a: "Kugabanya umuvuduko",
    b: "Gukomeza witonze",
    c: "Guhagarara by'ako kanya",
    d: "Gutegereza uburenganzira bwo gukomeza",
    answer: "c"
  },
  {
    id: 2,
    question: "Iki cyapa gisobanura iki?",
    image: "images/stop_sign.png",
    a: "Ntukanyure aha",
    b: "Hagarara",
    c: "Icyapa cyo kwitonda",
    d: "Inzira ya moto gusa",
    answer: "b"
  },
  {
    id: 3,
    question: "Ni ryari uburenganzira bwo gukomeza bugirwa n'umugenzi uri iburyo bwawe?",
    a: "Iyo ugenda buhoro",
    b: "Iyo wifuza guhindukira ibumoso",
    c: "Iyo ugeze ahahurira imihanda idafite icyapa",
    d: "Iyo imodoka ya polisi iri imbere",
    answer: "c"
  },
  {
    id: 4,
    question: "Iki cyapa kigaragaza iki?",
    image: "images/yield_sign.png",
    a: "Komeza nta kwitondera",
    b: "Gabanya umuvuduko",
    c: "Tanga inzira",
    d: "Ntukanyure",
    answer: "c"
  },
  {
    id: 5,
    question: "Iki cyapa kigaragaza iki?",
    image: "images/pedestrian_crossing.png",
    a: "Aho abanyamaguru bambukira",
    b: "Aho imodoka zihagarara",
    c: "Aho imodoka zihuta cyane",
    d: "Aho imodoka zibujijwe kunyura",
    answer: "a"
  },
  {
    id: 6,
    question: "Igihe ugeze aho umuhanda utemerewe kunyurwamo, ugomba gukora iki?",
    a: "Kunyura buhoro",
    b: "Kunyura ushishoza",
    c: "Guhindura inzira",
    d: "Kugenda uhagaze",
    answer: "c"
  },
  {
    id: 7,
    question: "Iki cyapa gishushanya iki?",
    image: "images/roundabout.png",
    a: "Ahari umuhanda ugoramye",
    b: "Ahahurira imihanda myinshi",
    c: "Agace karimo uruziga",
    d: "Ahantu haba icyapa cyihariye",
    answer: "c"
  },
  {
    id: 8,
    question: "Iyo ugeze ku gitambambuga kiriho icyapa cya STOP ariko nta modoka iri hafi, ugomba gukora iki?",
    a: "Kunyura kuko nta muntu uri hafi",
    b: "Kugabanya umuvuduko gusa",
    c: "Guhagarara n’ubwo nta modoka iriho",
    d: "Kwihutira kwambuka",
    answer: "c"
  },
  {
    id: 9,
    question: "Iki cyapa kigaragaza iki?",
    image: "images/no_entry.png",
    a: "Ntukahanyure",
    b: "Urahanyura",
    c: "Hanyuramo gusa imodoka nini",
    d: "Aho abanyamaguru banyura",
    answer: "a"
  },
  {
    id: 10,
    question: "Iyo uri gutambuka ahari abanyeshuri benshi, ugomba gukora iki?",
    a: "Kongera umuvuduko",
    b: "Kwatsa amatara",
    c: "Kugabanya umuvuduko no kwitegura guhagarara",
    d: "Gukomeza nk'uko bisanzwe",
    answer: "c"
  },

  // --- IBIBAZO BYONGEREWE ---

  {
    id: 11,
    question: "Icyapa cya 'No Parking' gisobanura iki?",
    image: "images/no_parking.png",
    a: "Ntibemerewe guhagarara",
    b: "Ntibemerewe guca",
    c: "Ntibemerewe kugenda",
    d: "Ntibemerewe guhindura icyerekezo",
    answer: "a"
  },
  {
    id: 12,
    question: "Ukoresha amatara y'inyuma atukura igihe iki?",
    a: "Mugihe utwaye imodoka mu buryo busanzwe",
    b: "Mugihe uhagaritse imodoka nijoro",
    c: "Mugihe ugiye guhindura icyerekezo",
    d: "Mugihe uri mu muhanda utari uw’imodoka",
    answer: "b"
  },
  {
    id: 13,
    question: "Igihe uri mu muhanda uhora usubiramo, ni ryari ugomba gutanga inzira?",
    a: "Iyo uri guhindukira ibumoso gusa",
    b: "Iyo hari abandi banyamaguru",
    c: "Iyo hari imodoka irenze umuvuduko wawe",
    d: "Iyo uhagaritse mu muhanda nyabagendwa",
    answer: "b"
  },
  {
    id: 14,
    question: "Ukoresha iki igihe uri kugenda mu muhanda wuzuyemo umwijima nijoro?",
    a: "Amatara y’imbere acanye cyane (high beams)",
    b: "Amatara y’imbere acanye buhoro (low beams)",
    c: "Amatara y’inyuma gusa",
    d: "Amatara y’umuhanda",
    answer: "b"
  },
  {
    id: 15,
    question: "Icyapa cya 'Roundabout' gisobanura iki?",
    image: "images/roundabout.png",
    a: "Uragomba guhagarara",
    b: "Hari uruziga ruri imbere",
    c: "Uragomba gutanga inzira ku modoka ziri mu ruziga",
    d: "Uragomba kwihuta",
    answer: "c"
  },
  {
    id: 16,
    question: "Iyo uri kwinjira mu muhanda ufunzwe, ugomba gukora iki?",
    a: "Kuhagarika imodoka imbere",
    b: "Gutegereza ko hari umuntu ugiye kugenda",
    c: "Kureba ibumoso no iburyo mbere yo kwinjira",
    d: "Kunyura vuba",
    answer: "c"
  },
  {
    id: 17,
    question: "Icyapa cya 'No Entry' gisobanura iki?",
    image: "images/no_entry.png",
    a: "Ntukinjire muri uwo muhanda",
    b: "Urahagaze gusa",
    c: "Urarembere",
    d: "Uragomba kugabanya umuvuduko",
    answer: "a"
  },
  {
    id: 18,
    question: "Igihe uhuye n'umuhanda ufite icyapa cya 'Yield', ugomba gukora iki?",
    a: "Guhita winjira muri uwo muhanda",
    b: "Guhagarara burundu",
    c: "Kureba neza niba nta modoka ibiri hafi mbere yo kwinjira",
    d: "Kwihuta",
    answer: "c"
  },
  {
    id: 19,
    question: "Ni ryari ugomba gukoresha interinette mu modoka yawe?",
    a: "Mugihe ugiye guhindura icyerekezo",
    b: "Iyo uri guhagarara gusa",
    c: "Iyo uhindura umuvuduko",
    d: "Iyo uri kwinjira mu muhanda",
    answer: "a"
  },
  {
    id: 20,
    question: "Iyo uri mu muhanda utemerewe kurenza umuvuduko, ugomba gukora iki?",
    a: "Kugabanya umuvuduko",
    b: "Kongera umuvuduko",
    c: "Kureka abandi banyuramo mbere",
    d: "Kwihuta cyane",
    answer: "a"
  },

  {
    id: 21,
    question: "Iki cyapa kigaragaza iki?",
    image: "images/pedestrian_crossing.png",
    a: "Aho abanyamaguru banyura",
    b: "Aho imodoka zihagarara",
    c: "Aho imodoka zihuta",
    d: "Ahantu habujijwe kunyura",
    answer: "a"
  },
  {
    id: 22,
    question: "Ukoresha iki gihe uri mu muhanda utemewe kunyuramo nijoro?",
    a: "Amatara y’imbere acanye cyane",
    b: "Amatara y’imbere acanye buhoro",
    c: "Amatara y’inyuma yaka amatara",
    d: "Ntukoreshe amatara",
    answer: "b"
  },
  {
    id: 23,
    question: "Icyapa cya 'No U-Turn' gisobanura iki?",
    a: "Ntibemerewe guhindura icyerekezo",
    b: "Uragomba guhindura icyerekezo",
    c: "Uragomba guhagarara",
    d: "Uragomba kwihuta",
    answer: "a"
  },
  {
    id: 24,
    question: "Igihe uhura n’imodoka itabashije kugenda, ugomba gukora iki?",
    a: "Kumuvamo",
    b: "Kumutegeka gutambuka",
    c: "Guhindura umuhanda niba bishoboka",
    d: "Gukomeza uko wari uri",
    answer: "c"
  },
  {
    id: 25,
    question: "Iyo uri mu muhanda utarimo icyapa icyo ari cyo cyose, ni ryari ugomba gutanga inzira?",
    a: "Iyo ugeze ku mfuruka",
    b: "Iyo hari abandi banyamaguru",
    c: "Iyo hari imodoka iri imbere",
    d: "Iyo uhagaritse gusa",
    answer: "a"
  },
  {
    id: 26,
    question: "Ni ryari ugomba gukoresha interineti yo kwerekana ko uri guhindukira?",
    a: "Buri gihe uri guhindura icyerekezo",
    b: "Mugihe ugiye kugenda gusa",
    c: "Iyo uhagaritse gusa",
    d: "Iyo uri mu muhanda utemerewe kunyuramo",
    answer: "a"
  },
  {
    id: 27,
    question: "Iyo hari icyapa cya 'Speed Limit 50', bivuze iki?",
    a: "Umuvuduko ntugomba kurenza 50 km/h",
    b: "Umuvuduko ugomba kuba munsi ya 50 km/h gusa nijoro",
    c: "Umuvuduko ugomba kuba hejuru ya 50 km/h",
    d: "Ntugomba kugenda munsi ya 50 km/h",
    answer: "a"
  },
  {
    id: 28,
    question: "Igihe uri guhindukira, ugomba kureba he mbere yo guhindukira?",
    a: "Ibumoso gusa",
    b: "Iburyo gusa",
    c: "Ibumoso no Iburyo",
    d: "Nta na ho",
    answer: "c"
  },
  {
    id: 29,
    question: "Iki cyapa cya 'No Overtaking' gisobanura iki?",
    image: "images/no_overtaking.png",
    a: "Ntibemerewe kurenza imodoka imbere",
    b: "Uragomba kurenza imodoka zose",
    c: "Uragomba guca ku ruhande rw’ibumoso gusa",
    d: "Ntibemerewe guhagarara",
    answer: "a"
  },
  {
    id: 30,
    question: "Ni ryari ugomba kugabanya umuvuduko cyane mu muhanda?",
    a: "Iyo uri kugera ku gitambambuga cy’abanyamaguru",
    b: "Iyo uhagaze mu muhanda",
    c: "Iyo uri mu muhanda uhagaze",
    d: "Iyo uri gutwara moto",
    answer: "a"
  },

  // Ibibazo bishya byo kongera kugeza 40

  {
    id: 31,
    question: "Icyapa cya 'No Horn' gisobanura iki?",
    a: "Ntibemerewe gukoresha inzugi za moto cyangwa imodoka",
    b: "Uragomba gukoresha inzogi",
    c: "Uragomba gukoresha inzogi gusa nijoro",
    d: "Ntugomba guhagarara",
    answer: "a"
  },
  {
    id: 32,
    question: "Iyo uri mu muhanda utemerewe kunyurwamo, ni ryari wemerewe kunyura?",
    a: "Nta gihe na kimwe",
    b: "Iyo hari impamvu zidasanzwe",
    c: "Iyo hari icyapa kibemerera",
    d: "Iyo uri mu muhanda munini",
    answer: "c"
  },
  {
    id: 33,
    question: "Igihe umugenzi arimo kwambuka umuhanda ku gitambambuga, ugomba gukora iki?",
    a: "Kumureka anyure neza",
    b: "Kwihutira kunyura",
    c: "Kumutegeka gusubira inyuma",
    d: "Kumubwira kwihuta",
    answer: "a"
  },
  {
    id: 34,
    question: "Ni iki ugomba gukora mugihe umugenzi uri ku muhanda ari kwinjira mu muhanda munini?",
    a: "Guhita uva mu muhanda",
    b: "Kumutegeka guhagarara",
    c: "Kumwereka inzira",
    d: "Kwihutira kwambuka",
    answer: "c"
  },
  {
    id: 35,
    question: "Igihe uri mu muhanda, ugomba gukora iki igihe hari imodoka iri inyuma ishaka kugucyura?",
    a: "Gutanga inzira no kugenda buhoro",
    b: "Kwihutira guhagarara",
    c: "Kwerekana ko utumva",
    d: "Kwimura imodoka y'imbere",
    answer: "a"
  },
  {
    id: 36,
    question: "Icyapa cya 'Stop Ahead' gisobanura iki?",
    a: "Hategerejwe icyapa cya STOP imbere",
    b: "Hagarara ubu",
    c: "Ntugomba guhagarara",
    d: "Genda buhoro",
    answer: "a"
  },
  {
    id: 37,
    question: "Ni ryari ugomba gukoresha umucyo w'amatara atukura ya nyuma?",
    a: "Mugihe uhagaze nijoro",
    b: "Mugihe uri mu muhanda munini",
    c: "Mugihe uri mu muhanda utemewe kunyurwamo",
    d: "Mugihe uri kugenda buhoro",
    answer: "a"
  },
  {
    id: 38,
    question: "Iyo uri mu muhanda uhindagurika, ugomba gukora iki?",
    a: "Kwitegura guhagarara",
    b: "Kwihuta cyane",
    c: "Kugendera hagati y'umuhanda",
    d: "Guhita uva mu muhanda",
    answer: "a"
  },
  {
    id: 39,
    question: "Ni iki ugomba gukora igihe uhura n’umodoka y’imbere ikoresha amatara atukura?",
    a: "Kugabanya umuvuduko no guhagarara niba bikenewe",
    b: "Kwihuta",
    c: "Kwimuka ku ruhande rw'ibumoso",
    d: "Kuyobya amatara",
    answer: "a"
  },
  {
    id: 40,
    question: "Iyo uri mu muhanda munini, ugomba kugendera he?",
    a: "Ku ruhande rw'iburyo",
    b: "Ku ruhande rw'ibumoso",
    c: "Mu mutima w'umuhanda",
    d: "Ahantu hose hatari icyapa",
    answer: "a"
  }
];