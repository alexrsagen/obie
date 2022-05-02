<?php namespace Obie\Http;

class Mime {
	public function __construct(
		public string $type = '',
		public string $subtype = '',
		public array $parameters = [],
	) {
		$this->type = strtolower(ltrim($type, "\n\r\t "));
		$this->subtype = strtolower(rtrim($subtype, "\n\r\t "));
	}

	const EXT_TYPES = [
		'.123' => 'application/vnd.lotus-1-2-3',
		'.a' => 'application/octet-stream',
		'.aab' => 'application/x-authorware-bin',
		'.aam' => 'application/x-authorware-map',
		'.aas' => 'application/x-authorware-seg',
		'.abw' => 'application/x-abiword',
		'.acc' => 'application/vnd.americandynamics.acc',
		'.ace' => 'application/x-ace-compressed',
		'.acu' => 'application/vnd.acucobol',
		'.acutc' => 'application/vnd.acucorp',
		'.aep' => 'application/vnd.audiograph',
		'.afm' => 'application/x-font-type1',
		'.afp' => 'application/vnd.ibm.modcap',
		'.ai' => 'application/postscript',
		'.air' => 'application/vnd.adobe.air-application-installer-package+zip',
		'.ami' => 'application/vnd.amiga.ami',
		'.apk' => 'application/vnd.android.package-archive',
		'.application' => 'application/x-ms-application',
		'.apr' => 'application/vnd.lotus-approach',
		'.asc' => 'application/pgp-signature',
		'.aso' => 'application/vnd.accpac.simply.aso',
		'.atc' => 'application/vnd.acucorp',
		'.atom' => 'application/atom+xml',
		'.atomcat' => 'application/atomcat+xml',
		'.atomsvc' => 'application/atomsvc+xml',
		'.atx' => 'application/vnd.antix.game-component',
		'.aw' => 'application/applixware',
		'.azf' => 'application/vnd.airzip.filesecure.azf',
		'.azs' => 'application/vnd.airzip.filesecure.azs',
		'.azw' => 'application/vnd.amazon.ebook',
		'.bat' => 'application/x-msdownload',
		'.bcpio' => 'application/x-bcpio',
		'.bdf' => 'application/x-font-bdf',
		'.bdm' => 'application/vnd.syncml.dm+wbxml',
		'.bh2' => 'application/vnd.fujitsu.oasysprs',
		'.bin' => 'application/octet-stream',
		'.bmi' => 'application/vnd.bmi',
		'.book' => 'application/vnd.framemaker',
		'.box' => 'application/vnd.previewsystems.box',
		'.boz' => 'application/x-bzip2',
		'.bpk' => 'application/octet-stream',
		'.bz' => 'application/x-bzip',
		'.bz2' => 'application/x-bzip2',
		'.c4d' => 'application/vnd.clonk.c4group',
		'.c4f' => 'application/vnd.clonk.c4group',
		'.c4g' => 'application/vnd.clonk.c4group',
		'.c4p' => 'application/vnd.clonk.c4group',
		'.c4u' => 'application/vnd.clonk.c4group',
		'.cab' => 'application/vnd.ms-cab-compressed',
		'.car' => 'application/vnd.curl.car',
		'.cat' => 'application/vnd.ms-pki.seccat',
		'.cct' => 'application/x-director',
		'.ccxml' => 'application/ccxml+xml',
		'.cdbcmsg' => 'application/vnd.contact.cmsg',
		'.cdf' => 'application/x-netcdf',
		'.cdkey' => 'application/vnd.mediastation.cdkey',
		'.cdxml' => 'application/vnd.chemdraw+xml',
		'.cdy' => 'application/vnd.cinderella',
		'.cer' => 'application/pkix-cert',
		'.chat' => 'application/x-chat',
		'.chm' => 'application/vnd.ms-htmlhelp',
		'.chrt' => 'application/vnd.kde.kchart',
		'.cii' => 'application/vnd.anser-web-certificate-issue-initiation',
		'.cil' => 'application/vnd.ms-artgalry',
		'.cla' => 'application/vnd.claymore',
		'.class' => 'application/java-vm',
		'.clkk' => 'application/vnd.crick.clicker.keyboard',
		'.clkp' => 'application/vnd.crick.clicker.palette',
		'.clkt' => 'application/vnd.crick.clicker.template',
		'.clkw' => 'application/vnd.crick.clicker.wordbank',
		'.clkx' => 'application/vnd.crick.clicker',
		'.clp' => 'application/x-msclip',
		'.cmc' => 'application/vnd.cosmocaller',
		'.cmp' => 'application/vnd.yellowriver-custom-menu',
		'.cod' => 'application/vnd.rim.cod',
		'.com' => 'application/x-msdownload',
		'.cpio' => 'application/x-cpio',
		'.cpt' => 'application/mac-compactpro',
		'.crd' => 'application/x-mscardfile',
		'.crl' => 'application/pkix-crl',
		'.crt' => 'application/x-x509-ca-cert',
		'.csh' => 'application/x-csh',
		'.csp' => 'application/vnd.commonspace',
		'.cst' => 'application/x-director',
		'.cu' => 'application/cu-seeme',
		'.cww' => 'application/prs.cww',
		'.cxt' => 'application/x-director',
		'.daf' => 'application/vnd.mobius.daf',
		'.dataless' => 'application/vnd.fdsn.seed',
		'.davmount' => 'application/davmount+xml',
		'.dcr' => 'application/x-director',
		'.dd2' => 'application/vnd.oma.dd2+xml',
		'.ddd' => 'application/vnd.fujixerox.ddd',
		'.deb' => 'application/x-debian-package',
		'.deploy' => 'application/octet-stream',
		'.der' => 'application/x-x509-ca-cert',
		'.dfac' => 'application/vnd.dreamfactory',
		'.dir' => 'application/x-director',
		'.dis' => 'application/vnd.mobius.dis',
		'.dist' => 'application/octet-stream',
		'.distz' => 'application/octet-stream',
		'.dll' => 'application/x-msdownload',
		'.dmg' => 'application/x-apple-diskimage',
		'.dms' => 'application/octet-stream',
		'.dna' => 'application/vnd.dna',
		'.doc' => 'application/msword',
		'.docm' => 'application/vnd.ms-word.document.macroenabled.12',
		'.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'.dot' => 'application/msword',
		'.dotm' => 'application/vnd.ms-word.template.macroenabled.12',
		'.dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
		'.dp' => 'application/vnd.osgi.dp',
		'.dpg' => 'application/vnd.dpgraph',
		'.dtb' => 'application/x-dtbook+xml',
		'.dtd' => 'application/xml-dtd',
		'.dump' => 'application/octet-stream',
		'.dvi' => 'application/x-dvi',
		'.dxp' => 'application/vnd.spotfire.dxp',
		'.dxr' => 'application/x-director',
		'.ecma' => 'application/ecmascript',
		'.edm' => 'application/vnd.novadigm.edm',
		'.edx' => 'application/vnd.novadigm.edx',
		'.efif' => 'application/vnd.picsel',
		'.ei6' => 'application/vnd.pg.osasli',
		'.elc' => 'application/octet-stream',
		'.emma' => 'application/emma+xml',
		'.eot' => 'application/vnd.ms-fontobject',
		'.eps' => 'application/postscript',
		'.epub' => 'application/epub+zip',
		'.es3' => 'application/vnd.eszigno3+xml',
		'.esf' => 'application/vnd.epson.esf',
		'.et3' => 'application/vnd.eszigno3+xml',
		'.exe' => 'application/x-msdownload',
		'.ext' => 'application/vnd.novadigm.ext',
		'.ez' => 'application/andrew-inset',
		'.ez2' => 'application/vnd.ezpix-album',
		'.ez3' => 'application/vnd.ezpix-package',
		'.fdf' => 'application/vnd.fdf',
		'.fe_launch' => 'application/vnd.denovo.fcselayout-link',
		'.fg5' => 'application/vnd.fujitsu.oasysgp',
		'.fgd' => 'application/x-director',
		'.fig' => 'application/x-xfig',
		'.flo' => 'application/vnd.micrografx.flo',
		'.flw' => 'application/vnd.kde.kivio',
		'.fm' => 'application/vnd.framemaker',
		'.fnc' => 'application/vnd.frogans.fnc',
		'.frame' => 'application/vnd.framemaker',
		'.fsc' => 'application/vnd.fsc.weblaunch',
		'.ftc' => 'application/vnd.fluxtime.clip',
		'.fti' => 'application/vnd.anser-web-funds-transfer-initiation',
		'.fzs' => 'application/vnd.fuzzysheet',
		'.gac' => 'application/vnd.groove-account',
		'.geo' => 'application/vnd.dynageo',
		'.gex' => 'application/vnd.geometry-explorer',
		'.ggb' => 'application/vnd.geogebra.file',
		'.ggt' => 'application/vnd.geogebra.tool',
		'.ghf' => 'application/vnd.groove-help',
		'.gim' => 'application/vnd.groove-identity-message',
		'.gmx' => 'application/vnd.gmx',
		'.gnumeric' => 'application/x-gnumeric',
		'.gph' => 'application/vnd.flographit',
		'.gqf' => 'application/vnd.grafeq',
		'.gqs' => 'application/vnd.grafeq',
		'.gram' => 'application/srgs',
		'.gre' => 'application/vnd.geometry-explorer',
		'.grv' => 'application/vnd.groove-injector',
		'.grxml' => 'application/srgs+xml',
		'.gsf' => 'application/x-font-ghostscript',
		'.gtar' => 'application/x-gtar',
		'.gtm' => 'application/vnd.groove-tool-message',
		'.gz' => 'application/x-gzip',
		'.hbci' => 'application/vnd.hbci',
		'.hdf' => 'application/x-hdf',
		'.hlp' => 'application/winhlp',
		'.hpgl' => 'application/vnd.hp-hpgl',
		'.hpid' => 'application/vnd.hp-hpid',
		'.hps' => 'application/vnd.hp-hps',
		'.hqx' => 'application/mac-binhex40',
		'.htke' => 'application/vnd.kenameaapp',
		'.hvd' => 'application/vnd.yamaha.hv-dic',
		'.hvp' => 'application/vnd.yamaha.hv-voice',
		'.hvs' => 'application/vnd.yamaha.hv-script',
		'.icc' => 'application/vnd.iccprofile',
		'.icm' => 'application/vnd.iccprofile',
		'.ifm' => 'application/vnd.shana.informed.formdata',
		'.igl' => 'application/vnd.igloader',
		'.igx' => 'application/vnd.micrografx.igx',
		'.iif' => 'application/vnd.shana.informed.interchange',
		'.imp' => 'application/vnd.accpac.simply.imp',
		'.ims' => 'application/vnd.ms-ims',
		'.ipk' => 'application/vnd.shana.informed.package',
		'.irm' => 'application/vnd.ibm.rights-management',
		'.irp' => 'application/vnd.irepository.package+xml',
		'.iso' => 'application/octet-stream',
		'.itp' => 'application/vnd.shana.informed.formtemplate',
		'.ivp' => 'application/vnd.immervision-ivp',
		'.ivu' => 'application/vnd.immervision-ivu',
		'.jam' => 'application/vnd.jam',
		'.jar' => 'application/java-archive',
		'.jisp' => 'application/vnd.jisp',
		'.jlt' => 'application/vnd.hp-jlyt',
		'.jnlp' => 'application/x-java-jnlp-file',
		'.joda' => 'application/vnd.joost.joda-archive',
		'.js' => 'application/javascript',
		'.json' => 'application/json',
		'.karbon' => 'application/vnd.kde.karbon',
		'.kfo' => 'application/vnd.kde.kformula',
		'.kia' => 'application/vnd.kidspiration',
		'.kil' => 'application/x-killustrator',
		'.kml' => 'application/vnd.google-earth.kml+xml',
		'.kmz' => 'application/vnd.google-earth.kmz',
		'.kne' => 'application/vnd.kinar',
		'.knp' => 'application/vnd.kinar',
		'.kon' => 'application/vnd.kde.kontour',
		'.kpr' => 'application/vnd.kde.kpresenter',
		'.kpt' => 'application/vnd.kde.kpresenter',
		'.ksp' => 'application/vnd.kde.kspread',
		'.ktr' => 'application/vnd.kahootz',
		'.ktz' => 'application/vnd.kahootz',
		'.kwd' => 'application/vnd.kde.kword',
		'.kwt' => 'application/vnd.kde.kword',
		'.latex' => 'application/x-latex',
		'.lbd' => 'application/vnd.llamagraphics.life-balance.desktop',
		'.lbe' => 'application/vnd.llamagraphics.life-balance.exchange+xml',
		'.les' => 'application/vnd.hhe.lesson-player',
		'.lha' => 'application/octet-stream',
		'.link66' => 'application/vnd.route66.link66+xml',
		'.list3820' => 'application/vnd.ibm.modcap',
		'.listafp' => 'application/vnd.ibm.modcap',
		'.lostxml' => 'application/lost+xml',
		'.lrf' => 'application/octet-stream',
		'.lrm' => 'application/vnd.ms-lrm',
		'.ltf' => 'application/vnd.frogans.ltf',
		'.lwp' => 'application/vnd.lotus-wordpro',
		'.lzh' => 'application/octet-stream',
		'.m13' => 'application/x-msmediaview',
		'.m14' => 'application/x-msmediaview',
		'.ma' => 'application/mathematica',
		'.mag' => 'application/vnd.ecowin.chart',
		'.maker' => 'application/vnd.framemaker',
		'.mathml' => 'application/mathml+xml',
		'.mb' => 'application/mathematica',
		'.mbk' => 'application/vnd.mobius.mbk',
		'.mbox' => 'application/mbox',
		'.mc1' => 'application/vnd.medcalcdata',
		'.mcd' => 'application/vnd.mcd',
		'.mdb' => 'application/x-msaccess',
		'.mfm' => 'application/vnd.mfmp',
		'.mgz' => 'application/vnd.proteus.magazine',
		'.mif' => 'application/vnd.mif',
		'.mlp' => 'application/vnd.dolby.mlp',
		'.mmd' => 'application/vnd.chipnuts.karaoke-mmd',
		'.mmf' => 'application/vnd.smaf',
		'.mny' => 'application/x-msmoney',
		'.mobi' => 'application/x-mobipocket-ebook',
		'.mp4s' => 'application/mp4',
		'.mpc' => 'application/vnd.mophun.certificate',
		'.mpkg' => 'application/vnd.apple.installer+xml',
		'.mpm' => 'application/vnd.blueice.multipass',
		'.mpn' => 'application/vnd.mophun.application',
		'.mpp' => 'application/vnd.ms-project',
		'.mpt' => 'application/vnd.ms-project',
		'.mpy' => 'application/vnd.ibm.minipay',
		'.mqy' => 'application/vnd.mobius.mqy',
		'.mrc' => 'application/marc',
		'.mscml' => 'application/mediaservercontrol+xml',
		'.mseed' => 'application/vnd.fdsn.mseed',
		'.mseq' => 'application/vnd.mseq',
		'.msf' => 'application/vnd.epson.msf',
		'.msi' => 'application/x-msdownload',
		'.msl' => 'application/vnd.mobius.msl',
		'.msty' => 'application/vnd.muvee.style',
		'.mus' => 'application/vnd.musician',
		'.musicxml' => 'application/vnd.recordare.musicxml+xml',
		'.mvb' => 'application/x-msmediaview',
		'.mwf' => 'application/vnd.mfer',
		'.mxf' => 'application/mxf',
		'.mxl' => 'application/vnd.recordare.musicxml',
		'.mxml' => 'application/xv+xml',
		'.mxs' => 'application/vnd.triscape.mxs',
		'.n-gage' => 'application/vnd.nokia.n-gage.symbian.install',
		'.nb' => 'application/mathematica',
		'.nc' => 'application/x-netcdf',
		'.ncx' => 'application/x-dtbncx+xml',
		'.ngdat' => 'application/vnd.nokia.n-gage.data',
		'.nlu' => 'application/vnd.neurolanguage.nlu',
		'.nml' => 'application/vnd.enliven',
		'.nnd' => 'application/vnd.noblenet-directory',
		'.nns' => 'application/vnd.noblenet-sealer',
		'.nnw' => 'application/vnd.noblenet-web',
		'.nsf' => 'application/vnd.lotus-notes',
		'.o' => 'application/octet-stream',
		'.oa2' => 'application/vnd.fujitsu.oasys2',
		'.oa3' => 'application/vnd.fujitsu.oasys3',
		'.oas' => 'application/vnd.fujitsu.oasys',
		'.obd' => 'application/x-msbinder',
		'.obj' => 'application/octet-stream',
		'.oda' => 'application/oda',
		'.odb' => 'application/vnd.oasis.opendocument.database',
		'.odc' => 'application/vnd.oasis.opendocument.chart',
		'.odf' => 'application/vnd.oasis.opendocument.formula',
		'.odft' => 'application/vnd.oasis.opendocument.formula-template',
		'.odg' => 'application/vnd.oasis.opendocument.graphics',
		'.odi' => 'application/vnd.oasis.opendocument.image',
		'.odp' => 'application/vnd.oasis.opendocument.presentation',
		'.ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		'.odt' => 'application/vnd.oasis.opendocument.text',
		'.ogx' => 'application/ogg',
		'.onepkg' => 'application/onenote',
		'.onetmp' => 'application/onenote',
		'.onetoc' => 'application/onenote',
		'.onetoc2' => 'application/onenote',
		'.opf' => 'application/oebps-package+xml',
		'.oprc' => 'application/vnd.palm',
		'.org' => 'application/vnd.lotus-organizer',
		'.osf' => 'application/vnd.yamaha.openscoreformat',
		'.osfpvg' => 'application/vnd.yamaha.openscoreformat.osfpvg+xml',
		'.otc' => 'application/vnd.oasis.opendocument.chart-template',
		'.otg' => 'application/vnd.oasis.opendocument.graphics-template',
		'.oth' => 'application/vnd.oasis.opendocument.text-web',
		'.oti' => 'application/vnd.oasis.opendocument.image-template',
		'.otm' => 'application/vnd.oasis.opendocument.text-master',
		'.otp' => 'application/vnd.oasis.opendocument.presentation-template',
		'.ots' => 'application/vnd.oasis.opendocument.spreadsheet-template',
		'.ott' => 'application/vnd.oasis.opendocument.text-template',
		'.oxt' => 'application/vnd.openofficeorg.extension',
		'.p10' => 'application/pkcs10',
		'.p12' => 'application/x-pkcs12',
		'.p7b' => 'application/x-pkcs7-certificates',
		'.p7c' => 'application/pkcs7-mime',
		'.p7m' => 'application/pkcs7-mime',
		'.p7r' => 'application/x-pkcs7-certreqresp',
		'.p7s' => 'application/pkcs7-signature',
		'.pbd' => 'application/vnd.powerbuilder6',
		'.pcf' => 'application/x-font-pcf',
		'.pcl' => 'application/vnd.hp-pcl',
		'.pclxl' => 'application/vnd.hp-pclxl',
		'.pcurl' => 'application/vnd.curl.pcurl',
		'.pdb' => 'application/vnd.palm',
		'.pdf' => 'application/pdf',
		'.pfa' => 'application/x-font-type1',
		'.pfb' => 'application/x-font-type1',
		'.pfm' => 'application/x-font-type1',
		'.pfr' => 'application/font-tdpfr',
		'.pfx' => 'application/x-pkcs12',
		'.pgn' => 'application/x-chess-pgn',
		'.pgp' => 'application/pgp-encrypted',
		'.pkg' => 'application/octet-stream',
		'.pki' => 'application/pkixcmp',
		'.pkipath' => 'application/pkix-pkipath',
		'.plb' => 'application/vnd.3gpp.pic-bw-large',
		'.plc' => 'application/vnd.mobius.plc',
		'.plf' => 'application/vnd.pocketlearn',
		'.pls' => 'application/pls+xml',
		'.pml' => 'application/vnd.ctc-posml',
		'.portpkg' => 'application/vnd.macports.portpkg',
		'.pot' => 'application/vnd.ms-powerpoint',
		'.potm' => 'application/vnd.ms-powerpoint.template.macroenabled.12',
		'.potx' => 'application/vnd.openxmlformats-officedocument.presentationml.template',
		'.ppa' => 'application/vnd.ms-powerpoint',
		'.ppam' => 'application/vnd.ms-powerpoint.addin.macroenabled.12',
		'.ppd' => 'application/vnd.cups-ppd',
		'.pps' => 'application/vnd.ms-powerpoint',
		'.ppsm' => 'application/vnd.ms-powerpoint.slideshow.macroenabled.12',
		'.ppsx' => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
		'.ppt' => 'application/vnd.ms-powerpoint',
		'.pptm' => 'application/vnd.ms-powerpoint.presentation.macroenabled.12',
		'.pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'.pqa' => 'application/vnd.palm',
		'.prc' => 'application/x-mobipocket-ebook',
		'.pre' => 'application/vnd.lotus-freelance',
		'.prf' => 'application/pics-rules',
		'.ps' => 'application/postscript',
		'.psb' => 'application/vnd.3gpp.pic-bw-small',
		'.psf' => 'application/x-font-linux-psf',
		'.ptid' => 'application/vnd.pvi.ptid1',
		'.pub' => 'application/x-mspublisher',
		'.pvb' => 'application/vnd.3gpp.pic-bw-var',
		'.pwn' => 'application/vnd.3m.post-it-notes',
		'.pwz' => 'application/vnd.ms-powerpoint',
		'.pyc' => 'application/x-python-code',
		'.pyo' => 'application/x-python-code',
		'.qam' => 'application/vnd.epson.quickanime',
		'.qbo' => 'application/vnd.intu.qbo',
		'.qfx' => 'application/vnd.intu.qfx',
		'.qps' => 'application/vnd.publishare-delta-tree',
		'.qwd' => 'application/vnd.quark.quarkxpress',
		'.qwt' => 'application/vnd.quark.quarkxpress',
		'.qxb' => 'application/vnd.quark.quarkxpress',
		'.qxd' => 'application/vnd.quark.quarkxpress',
		'.qxl' => 'application/vnd.quark.quarkxpress',
		'.qxt' => 'application/vnd.quark.quarkxpress',
		'.rar' => 'application/x-rar-compressed',
		'.rcprofile' => 'application/vnd.ipunplugged.rcprofile',
		'.rdf' => 'application/rdf+xml',
		'.rdz' => 'application/vnd.data-vision.rdz',
		'.rep' => 'application/vnd.businessobjects',
		'.res' => 'application/x-dtbresource+xml',
		'.rif' => 'application/reginfo+xml',
		'.rl' => 'application/resource-lists+xml',
		'.rld' => 'application/resource-lists-diff+xml',
		'.rm' => 'application/vnd.rn-realmedia',
		'.rms' => 'application/vnd.jcp.javame.midlet-rms',
		'.rnc' => 'application/relax-ng-compact-syntax',
		'.rpm' => 'application/x-rpm',
		'.rpss' => 'application/vnd.nokia.radio-presets',
		'.rpst' => 'application/vnd.nokia.radio-preset',
		'.rq' => 'application/sparql-query',
		'.rs' => 'application/rls-services+xml',
		'.rsd' => 'application/rsd+xml',
		'.rss' => 'application/rss+xml',
		'.rtf' => 'application/rtf',
		'.saf' => 'application/vnd.yamaha.smaf-audio',
		'.sbml' => 'application/sbml+xml',
		'.sc' => 'application/vnd.ibm.secure-container',
		'.scd' => 'application/x-msschedule',
		'.scm' => 'application/vnd.lotus-screencam',
		'.scq' => 'application/scvp-cv-request',
		'.scs' => 'application/scvp-cv-response',
		'.sda' => 'application/vnd.stardivision.draw',
		'.sdc' => 'application/vnd.stardivision.calc',
		'.sdd' => 'application/vnd.stardivision.impress',
		'.sdkd' => 'application/vnd.solent.sdkm+xml',
		'.sdkm' => 'application/vnd.solent.sdkm+xml',
		'.sdp' => 'application/sdp',
		'.sdw' => 'application/vnd.stardivision.writer',
		'.see' => 'application/vnd.seemail',
		'.seed' => 'application/vnd.fdsn.seed',
		'.sema' => 'application/vnd.sema',
		'.semd' => 'application/vnd.semd',
		'.semf' => 'application/vnd.semf',
		'.ser' => 'application/java-serialized-object',
		'.setpay' => 'application/set-payment-initiation',
		'.setreg' => 'application/set-registration-initiation',
		'.sfd-hdstx' => 'application/vnd.hydrostatix.sof-data',
		'.sfs' => 'application/vnd.spotfire.sfs',
		'.sgl' => 'application/vnd.stardivision.writer-global',
		'.sh' => 'application/x-sh',
		'.shar' => 'application/x-shar',
		'.shf' => 'application/shf+xml',
		'.sic' => 'application/vnd.wap.sic',
		'.sig' => 'application/pgp-signature',
		'.sis' => 'application/vnd.symbian.install',
		'.sisx' => 'application/vnd.symbian.install',
		'.sit' => 'application/x-stuffit',
		'.sitx' => 'application/x-stuffitx',
		'.skd' => 'application/vnd.koan',
		'.skm' => 'application/vnd.koan',
		'.skp' => 'application/vnd.koan',
		'.skt' => 'application/vnd.koan',
		'.slc' => 'application/vnd.wap.slc',
		'.sldm' => 'application/vnd.ms-powerpoint.slide.macroenabled.12',
		'.sldx' => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
		'.slt' => 'application/vnd.epson.salt',
		'.smf' => 'application/vnd.stardivision.math',
		'.smi' => 'application/smil+xml',
		'.smil' => 'application/smil+xml',
		'.snf' => 'application/x-font-snf',
		'.so' => 'application/octet-stream',
		'.spc' => 'application/x-pkcs7-certificates',
		'.spf' => 'application/vnd.yamaha.smaf-phrase',
		'.spl' => 'application/x-futuresplash',
		'.spp' => 'application/scvp-vp-response',
		'.spq' => 'application/scvp-vp-request',
		'.src' => 'application/x-wais-source',
		'.srx' => 'application/sparql-results+xml',
		'.sse' => 'application/vnd.kodak-descriptor',
		'.ssf' => 'application/vnd.epson.ssf',
		'.ssml' => 'application/ssml+xml',
		'.stc' => 'application/vnd.sun.xml.calc.template',
		'.std' => 'application/vnd.sun.xml.draw.template',
		'.stf' => 'application/vnd.wt.stf',
		'.sti' => 'application/vnd.sun.xml.impress.template',
		'.stk' => 'application/hyperstudio',
		'.stl' => 'application/vnd.ms-pki.stl',
		'.str' => 'application/vnd.pg.format',
		'.stw' => 'application/vnd.sun.xml.writer.template',
		'.sus' => 'application/vnd.sus-calendar',
		'.susp' => 'application/vnd.sus-calendar',
		'.sv4cpio' => 'application/x-sv4cpio',
		'.sv4crc' => 'application/x-sv4crc',
		'.svd' => 'application/vnd.svd',
		'.swa' => 'application/x-director',
		'.swf' => 'application/x-shockwave-flash',
		'.swi' => 'application/vnd.arastra.swi',
		'.sxc' => 'application/vnd.sun.xml.calc',
		'.sxd' => 'application/vnd.sun.xml.draw',
		'.sxg' => 'application/vnd.sun.xml.writer.global',
		'.sxi' => 'application/vnd.sun.xml.impress',
		'.sxm' => 'application/vnd.sun.xml.math',
		'.sxw' => 'application/vnd.sun.xml.writer',
		'.tao' => 'application/vnd.tao.intent-module-archive',
		'.tar' => 'application/x-tar',
		'.tcap' => 'application/vnd.3gpp2.tcap',
		'.tcl' => 'application/x-tcl',
		'.teacher' => 'application/vnd.smart.teacher',
		'.tex' => 'application/x-tex',
		'.texi' => 'application/x-texinfo',
		'.texinfo' => 'application/x-texinfo',
		'.tfm' => 'application/x-tex-tfm',
		'.tgz' => 'application/x-gzip',
		'.tmo' => 'application/vnd.tmobile-livetv',
		'.torrent' => 'application/x-bittorrent',
		'.tpl' => 'application/vnd.groove-tool-template',
		'.tpt' => 'application/vnd.trid.tpt',
		'.tra' => 'application/vnd.trueapp',
		'.trm' => 'application/x-msterminal',
		'.twd' => 'application/vnd.simtech-mindmapper',
		'.twds' => 'application/vnd.simtech-mindmapper',
		'.txd' => 'application/vnd.genomatix.tuxedo',
		'.txf' => 'application/vnd.mobius.txf',
		'.u32' => 'application/x-authorware-bin',
		'.udeb' => 'application/x-debian-package',
		'.ufd' => 'application/vnd.ufdl',
		'.ufdl' => 'application/vnd.ufdl',
		'.umj' => 'application/vnd.umajin',
		'.unityweb' => 'application/vnd.unity',
		'.uoml' => 'application/vnd.uoml+xml',
		'.ustar' => 'application/x-ustar',
		'.utz' => 'application/vnd.uiq.theme',
		'.vcd' => 'application/x-cdlink',
		'.vcg' => 'application/vnd.groove-vcard',
		'.vcx' => 'application/vnd.vcx',
		'.vis' => 'application/vnd.visionary',
		'.vor' => 'application/vnd.stardivision.writer',
		'.vox' => 'application/x-authorware-bin',
		'.vsd' => 'application/vnd.visio',
		'.vsf' => 'application/vnd.vsf',
		'.vss' => 'application/vnd.visio',
		'.vst' => 'application/vnd.visio',
		'.vsw' => 'application/vnd.visio',
		'.vxml' => 'application/voicexml+xml',
		'.w3d' => 'application/x-director',
		'.wad' => 'application/x-doom',
		'.wbs' => 'application/vnd.criticaltools.wbs+xml',
		'.wbxml' => 'application/vnd.wap.wbxml',
		'.wcm' => 'application/vnd.ms-works',
		'.wdb' => 'application/vnd.ms-works',
		'.wiz' => 'application/msword',
		'.wks' => 'application/vnd.ms-works',
		'.wmd' => 'application/x-ms-wmd',
		'.wmf' => 'application/x-msmetafile',
		'.wmlc' => 'application/vnd.wap.wmlc',
		'.wmlsc' => 'application/vnd.wap.wmlscriptc',
		'.wmz' => 'application/x-ms-wmz',
		'.wpd' => 'application/vnd.wordperfect',
		'.wpl' => 'application/vnd.ms-wpl',
		'.wps' => 'application/vnd.ms-works',
		'.wqd' => 'application/vnd.wqd',
		'.wri' => 'application/x-mswrite',
		'.wsdl' => 'application/wsdl+xml',
		'.wspolicy' => 'application/wspolicy+xml',
		'.wtb' => 'application/vnd.webturbo',
		'.x32' => 'application/x-authorware-bin',
		'.x3d' => 'application/vnd.hzn-3d-crossword',
		'.xap' => 'application/x-silverlight-app',
		'.xar' => 'application/vnd.xara',
		'.xbap' => 'application/x-ms-xbap',
		'.xbd' => 'application/vnd.fujixerox.docuworks.binder',
		'.xdm' => 'application/vnd.syncml.dm+xml',
		'.xdp' => 'application/vnd.adobe.xdp+xml',
		'.xdw' => 'application/vnd.fujixerox.docuworks',
		'.xenc' => 'application/xenc+xml',
		'.xer' => 'application/patch-ops-error+xml',
		'.xfdf' => 'application/vnd.adobe.xfdf',
		'.xfdl' => 'application/vnd.xfdl',
		'.xht' => 'application/xhtml+xml',
		'.xhtml' => 'application/xhtml+xml',
		'.xhvml' => 'application/xv+xml',
		'.xla' => 'application/vnd.ms-excel',
		'.xlam' => 'application/vnd.ms-excel.addin.macroenabled.12',
		'.xlb' => 'application/vnd.ms-excel',
		'.xlc' => 'application/vnd.ms-excel',
		'.xlm' => 'application/vnd.ms-excel',
		'.xls' => 'application/vnd.ms-excel',
		'.xlsb' => 'application/vnd.ms-excel.sheet.binary.macroenabled.12',
		'.xlsm' => 'application/vnd.ms-excel.sheet.macroenabled.12',
		'.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'.xlt' => 'application/vnd.ms-excel',
		'.xltm' => 'application/vnd.ms-excel.template.macroenabled.12',
		'.xltx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
		'.xlw' => 'application/vnd.ms-excel',
		'.xml' => 'application/xml',
		'.xo' => 'application/vnd.olpc-sugar',
		'.xop' => 'application/xop+xml',
		'.xpdl' => 'application/xml',
		'.xpi' => 'application/x-xpinstall',
		'.xpr' => 'application/vnd.is-xpr',
		'.xps' => 'application/vnd.ms-xpsdocument',
		'.xpw' => 'application/vnd.intercon.formnet',
		'.xpx' => 'application/vnd.intercon.formnet',
		'.xsl' => 'application/xml',
		'.xslt' => 'application/xslt+xml',
		'.xsm' => 'application/vnd.syncml+xml',
		'.xspf' => 'application/xspf+xml',
		'.xul' => 'application/vnd.mozilla.xul+xml',
		'.xvm' => 'application/xv+xml',
		'.xvml' => 'application/xv+xml',
		'.zaz' => 'application/vnd.zzazz.deck+xml',
		'.zip' => 'application/zip',
		'.zir' => 'application/vnd.zul',
		'.zirz' => 'application/vnd.zul',
		'.zmm' => 'application/vnd.handheld-entertainment+xml',
		'.webmanifest' => 'application/manifest+json',
		'.webapp' => 'x-web-app-manifest+json',
		'.7z' => 'application/x-7z-compressed',
		'.rar' => 'application/x-rar-compressed',
		'.aac' => 'audio/x-aac',
		'.adp' => 'audio/adpcm',
		'.aif' => 'audio/x-aiff',
		'.aifc' => 'audio/x-aiff',
		'.aiff' => 'audio/x-aiff',
		'.au' => 'audio/basic',
		'.dts' => 'audio/vnd.dts',
		'.dtshd' => 'audio/vnd.dts.hd',
		'.ecelp4800' => 'audio/vnd.nuera.ecelp4800',
		'.ecelp7470' => 'audio/vnd.nuera.ecelp7470',
		'.ecelp9600' => 'audio/vnd.nuera.ecelp9600',
		'.eol' => 'audio/vnd.digital-winds',
		'.kar' => 'audio/midi',
		'.lvp' => 'audio/vnd.lucent.voice',
		'.m2a' => 'audio/mpeg',
		'.m3a' => 'audio/mpeg',
		'.m3u' => 'audio/x-mpegurl',
		'.mid' => 'audio/midi',
		'.midi' => 'audio/midi',
		'.mp2' => 'audio/mpeg',
		'.mp2a' => 'audio/mpeg',
		'.mp3' => 'audio/mpeg',
		'.mp4a' => 'audio/mp4',
		'.mpga' => 'audio/mpeg',
		'.oga' => 'audio/ogg',
		'.ogg' => 'audio/ogg',
		'.pya' => 'audio/vnd.ms-playready.media.pya',
		'.ra' => 'audio/x-pn-realaudio',
		'.ram' => 'audio/x-pn-realaudio',
		'.rmi' => 'audio/midi',
		'.rmp' => 'audio/x-pn-realaudio-plugin',
		'.snd' => 'audio/basic',
		'.spx' => 'audio/ogg',
		'.wav' => 'audio/x-wav',
		'.wax' => 'audio/x-ms-wax',
		'.wma' => 'audio/x-ms-wma',
		'.cff' => 'font/collection',
		'.otf' => 'font/opentype',
		'.sfnt' => 'font/sfnt',
		'.ttc' => 'font/ttf',
		'.ttf' => 'font/ttf',
		'.woff' => 'font/woff',
		'.woff2' => 'font/woff2',
		'.bmp' => 'image/bmp',
		'.btif' => 'image/prs.btif',
		'.cgm' => 'image/cgm',
		'.cmx' => 'image/x-cmx',
		'.djv' => 'image/vnd.djvu',
		'.djvu' => 'image/vnd.djvu',
		'.dwg' => 'image/vnd.dwg',
		'.dxf' => 'image/vnd.dxf',
		'.fbs' => 'image/vnd.fastbidsheet',
		'.fh' => 'image/x-freehand',
		'.fh4' => 'image/x-freehand',
		'.fh5' => 'image/x-freehand',
		'.fh7' => 'image/x-freehand',
		'.fhc' => 'image/x-freehand',
		'.fpx' => 'image/vnd.fpx',
		'.fst' => 'image/vnd.fst',
		'.g3' => 'image/g3fax',
		'.gif' => 'image/gif',
		'.ico' => 'image/x-icon',
		'.ief' => 'image/ief',
		'.jpe' => 'image/jpeg',
		'.jpeg' => 'image/jpeg',
		'.jpg' => 'image/jpeg',
		'.mdi' => 'image/vnd.ms-modi',
		'.mmr' => 'image/vnd.fujixerox.edmics-mmr',
		'.npx' => 'image/vnd.net-fpx',
		'.pbm' => 'image/x-portable-bitmap',
		'.pct' => 'image/x-pict',
		'.pcx' => 'image/x-pcx',
		'.pgm' => 'image/x-portable-graymap',
		'.pic' => 'image/x-pict',
		'.apng' => 'image/apng',
		'.png' => 'image/png',
		'.pnm' => 'image/x-portable-anymap',
		'.ppm' => 'image/x-portable-pixmap',
		'.psd' => 'image/vnd.adobe.photoshop',
		'.ras' => 'image/x-cmu-raster',
		'.rgb' => 'image/x-rgb',
		'.rlc' => 'image/vnd.fujixerox.edmics-rlc',
		'.svg' => 'image/svg+xml',
		'.svgz' => 'image/svg+xml',
		'.tif' => 'image/tiff',
		'.tiff' => 'image/tiff',
		'.wbmp' => 'image/vnd.wap.wbmp',
		'.webp' => 'image/webp',
		'.xbm' => 'image/x-xbitmap',
		'.xif' => 'image/vnd.xiff',
		'.xpm' => 'image/x-xpixmap',
		'.xwd' => 'image/x-xwindowdump',
		'.eml' => 'message/rfc822',
		'.mht' => 'message/rfc822',
		'.mhtml' => 'message/rfc822',
		'.mime' => 'message/rfc822',
		'.nws' => 'message/rfc822',
		'.dwf' => 'model/vnd.dwf',
		'.gdl' => 'model/vnd.gdl',
		'.gtw' => 'model/vnd.gtw',
		'.iges' => 'model/iges',
		'.igs' => 'model/iges',
		'.mesh' => 'model/mesh',
		'.msh' => 'model/mesh',
		'.mts' => 'model/vnd.mts',
		'.silo' => 'model/mesh',
		'.vrml' => 'model/vrml',
		'.vtu' => 'model/vnd.vtu',
		'.wrl' => 'model/vrml',
		'.3dml' => 'text/vnd.in3d.3dml',
		'.asm' => 'text/x-asm',
		'.conf' => 'text/plain',
		'.css' => 'text/css',
		'.csv' => 'text/csv',
		'.curl' => 'text/vnd.curl',
		'.dcurl' => 'text/vnd.curl.dcurl',
		'.def' => 'text/plain',
		'.diff' => 'text/plain',
		'.dsc' => 'text/prs.lines.tag',
		'.etx' => 'text/x-setext',
		'.f' => 'text/x-fortran',
		'.f77' => 'text/x-fortran',
		'.f90' => 'text/x-fortran',
		'.flx' => 'text/vnd.fmi.flexstor',
		'.fly' => 'text/vnd.fly',
		'.for' => 'text/x-fortran',
		'.gv' => 'text/vnd.graphviz',
		'.htm' => 'text/html',
		'.html' => 'text/html',
		'.ics' => 'text/calendar',
		'.ifb' => 'text/calendar',
		'.in' => 'text/plain',
		'.jad' => 'text/vnd.sun.j2me.app-descriptor',
		'.java' => 'text/x-java-source',
		'.ksh' => 'text/plain',
		'.list' => 'text/plain',
		'.log' => 'text/plain',
		'.man' => 'text/troff',
		'.mcurl' => 'text/vnd.curl.mcurl',
		'.me' => 'text/troff',
		'.ms' => 'text/troff',
		'.p' => 'text/x-pascal',
		'.pas' => 'text/x-pascal',
		'.pl' => 'text/plain',
		'.py' => 'text/x-python',
		'.roff' => 'text/troff',
		'.rtx' => 'text/richtext',
		'.s' => 'text/x-asm',
		'.scurl' => 'text/vnd.curl.scurl',
		'.sgm' => 'text/sgml',
		'.sgml' => 'text/sgml',
		'.si' => 'text/vnd.wap.si',
		'.sl' => 'text/vnd.wap.sl',
		'.spot' => 'text/vnd.in3d.spot',
		'.t' => 'text/troff',
		'.text' => 'text/plain',
		'.tr' => 'text/troff',
		'.tsv' => 'text/tab-separated-values',
		'.txt' => 'text/plain',
		'.uri' => 'text/uri-list',
		'.uris' => 'text/uri-list',
		'.urls' => 'text/uri-list',
		'.uu' => 'text/x-uuencode',
		'.vcf' => 'text/x-vcard',
		'.vcs' => 'text/x-vcalendar',
		'.wml' => 'text/vnd.wap.wml',
		'.wmls' => 'text/vnd.wap.wmlscript',
		'.appcache' => 'text/cache-manifest',
		'.3g2' => 'video/3gpp2',
		'.3gp' => 'video/3gpp',
		'.3gpp' => 'video/3gpp',
		'.asf' => 'video/x-ms-asf',
		'.asx' => 'video/x-ms-asf',
		'.avi' => 'video/x-msvideo',
		'.f4v' => 'video/x-f4v',
		'.fli' => 'video/x-fli',
		'.flv' => 'video/x-flv',
		'.fvt' => 'video/vnd.fvt',
		'.h261' => 'video/h261',
		'.h263' => 'video/h263',
		'.h264' => 'video/h264',
		'.jpgm' => 'video/jpm',
		'.jpgv' => 'video/jpeg',
		'.jpm' => 'video/jpm',
		'.m1v' => 'video/mpeg',
		'.m2v' => 'video/mpeg',
		'.m4u' => 'video/vnd.mpegurl',
		'.m4v' => 'video/x-m4v',
		'.mj2' => 'video/mj2',
		'.mjp2' => 'video/mj2',
		'.mov' => 'video/quicktime',
		'.movie' => 'video/x-sgi-movie',
		'.mp4' => 'video/mp4',
		'.mp4v' => 'video/mp4',
		'.mpa' => 'video/mpeg',
		'.mpe' => 'video/mpeg',
		'.mpeg' => 'video/mpeg',
		'.mpg' => 'video/mpeg',
		'.mpg4' => 'video/mp4',
		'.mxu' => 'video/vnd.mpegurl',
		'.ogv' => 'video/ogg',
		'.pyv' => 'video/vnd.ms-playready.media.pyv',
		'.qt' => 'video/quicktime',
		'.viv' => 'video/vnd.vivo',
		'.webm' => 'video/webm',
		'.wm' => 'video/x-ms-wm',
		'.wmv' => 'video/x-ms-wmv',
		'.wmx' => 'video/x-ms-wmx',
		'.wvx' => 'video/x-ms-wvx',
		'.mkv' => 'video/x-matroska',
		'.mk3d' => 'video/x-matroska',
		'.mks' => 'video/x-matroska',
		'.cdx' => 'chemical/x-cdx',
		'.cif' => 'chemical/x-cif',
		'.cmdf' => 'chemical/x-cmdf',
		'.cml' => 'chemical/x-cml',
		'.csml' => 'chemical/x-csml',
		'.xyz' => 'chemical/x-xyz',
		'.ice' => 'x-conference/x-cooltalk',
	];

	const DEFAULT_TYPE = 'application/octet-stream';

	const EXT_KINDS = [
		'.apng' => self::KIND_IMG,
		'.bmp' => self::KIND_IMG,
		'.gif' => self::KIND_IMG,
		'.ico' => self::KIND_IMG,
		'.jpe' => self::KIND_IMG,
		'.jpeg' => self::KIND_IMG,
		'.jpg' => self::KIND_IMG,
		'.png' => self::KIND_IMG,
		'.svg' => self::KIND_IMG,
		'.webp' => self::KIND_IMG,
		'.asm' => self::KIND_TXT,
		'.conf' => self::KIND_TXT,
		'.css' => self::KIND_TXT,
		'.csv' => self::KIND_TXT,
		'.curl' => self::KIND_TXT,
		'.dcurl' => self::KIND_TXT,
		'.def' => self::KIND_TXT,
		'.diff' => self::KIND_TXT,
		'.dsc' => self::KIND_TXT,
		'.etx' => self::KIND_TXT,
		'.f' => self::KIND_TXT,
		'.f77' => self::KIND_TXT,
		'.f90' => self::KIND_TXT,
		'.flx' => self::KIND_TXT,
		'.fly' => self::KIND_TXT,
		'.for' => self::KIND_TXT,
		'.gv' => self::KIND_TXT,
		'.htm' => self::KIND_TXT,
		'.html' => self::KIND_TXT,
		'.ics' => self::KIND_TXT,
		'.ifb' => self::KIND_TXT,
		'.in' => self::KIND_TXT,
		'.jad' => self::KIND_TXT,
		'.java' => self::KIND_TXT,
		'.ksh' => self::KIND_TXT,
		'.list' => self::KIND_TXT,
		'.log' => self::KIND_TXT,
		'.man' => self::KIND_TXT,
		'.mcurl' => self::KIND_TXT,
		'.me' => self::KIND_TXT,
		'.ms' => self::KIND_TXT,
		'.p' => self::KIND_TXT,
		'.pas' => self::KIND_TXT,
		'.pl' => self::KIND_TXT,
		'.py' => self::KIND_TXT,
		'.roff' => self::KIND_TXT,
		'.rtx' => self::KIND_TXT,
		'.s' => self::KIND_TXT,
		'.scurl' => self::KIND_TXT,
		'.sgm' => self::KIND_TXT,
		'.sgml' => self::KIND_TXT,
		'.si' => self::KIND_TXT,
		'.sl' => self::KIND_TXT,
		'.spot' => self::KIND_TXT,
		'.t' => self::KIND_TXT,
		'.text' => self::KIND_TXT,
		'.tr' => self::KIND_TXT,
		'.tsv' => self::KIND_TXT,
		'.txt' => self::KIND_TXT,
		'.uri' => self::KIND_TXT,
		'.uris' => self::KIND_TXT,
		'.urls' => self::KIND_TXT,
		'.uu' => self::KIND_TXT,
		'.vcf' => self::KIND_TXT,
		'.vcs' => self::KIND_TXT,
		'.wml' => self::KIND_TXT,
		'.wmls' => self::KIND_TXT,
		'.appcache' => self::KIND_TXT,
		'.webmanifest' => self::KIND_TXT,
		'.webapp' => self::KIND_TXT,
		'.json' => self::KIND_TXT,
		'.js' => self::KIND_TXT,
		'.xml' => self::KIND_TXT,
		'.sh' => self::KIND_TXT,
		'.csh' => self::KIND_TXT,
		'.go' => self::KIND_TXT,
		'.c' => self::KIND_TXT,
		'.cc' => self::KIND_TXT,
		'.cpp' => self::KIND_TXT,
		'.cxx' => self::KIND_TXT,
		'.dic' => self::KIND_TXT,
		'.h' => self::KIND_TXT,
		'.hh' => self::KIND_TXT,
		'.sql' => self::KIND_TXT,
		'.lua' => self::KIND_TXT,
		'.md' => self::KIND_TXT,
		'.ps1' => self::KIND_TXT,
		'.psm' => self::KIND_TXT,
		'.flac' => self::KIND_AUDIO,
		'.mp3' => self::KIND_AUDIO,
		'.ogg' => self::KIND_AUDIO,
		'.oga' => self::KIND_AUDIO,
		'.wav' => self::KIND_AUDIO,
		'.3gp' => self::KIND_VIDEO,
		'.3gpp' => self::KIND_VIDEO,
		'.flv' => self::KIND_VIDEO,
		'.mp4' => self::KIND_VIDEO,
		'.m4v' => self::KIND_VIDEO,
		'.mp4v' => self::KIND_VIDEO,
		'.ogv' => self::KIND_VIDEO,
		'.webm' => self::KIND_VIDEO,
	];

	const KIND_IMG = 'img';
	const KIND_TXT = 'txt';
	const KIND_AUDIO = 'audio';
	const KIND_VIDEO = 'video';
	const KIND_FILE = 'file';

	// token := 1*<any (US-ASCII) CHAR except SPACE, CTLs, or tspecials>
	const TOKEN_CODEPOINT = [
		"!", "#", "$", "%", "&", "'", "*", "+",
		"-", ".", "^", "_", "`", "|", "~", "0",
		"1", "2", "3", "4", "5", "6", "7", "8",
		"9", "A", "B", "C", "D", "E", "F", "G",
		"H", "I", "J", "K", "L", "M", "N", "O",
		"P", "Q", "R", "S", "T", "U", "V", "W",
		"X", "Y", "Z", "a", "b", "c", "d", "e",
		"f", "g", "h", "i", "j", "k", "l", "m",
		"n", "o", "p", "q", "r", "s", "t", "u",
		"v", "w", "x", "y", "z",
	];

	public static function getKindByExtension(string $ext): string {
		return array_key_exists($ext, static::EXT_KINDS) ? static::EXT_KINDS[$ext] : self::KIND_FILE;
	}

	public static function getKindByFilename(string $filename): string {
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		return static::getKindByExtension($ext);
	}

	public static function getTypeByExtension(string $ext): string {
		return array_key_exists($ext, static::EXT_TYPES) ? static::EXT_TYPES[$ext] : self::DEFAULT_TYPE;
	}

	public static function getTypeByFilename(string $filename): string {
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		return static::getTypeByExtension($ext);
	}

	public static function getByExtension(string $ext): static {
		return array_key_exists($ext, static::EXT_TYPES) ? static::EXT_TYPES[$ext] : self::DEFAULT_TYPE;
	}

	public static function getByFilename(string $filename): static {
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		return static::getByExtension($ext);
	}

	protected static function isHttpToken(string $input): bool {
		for ($i = 0; $i < strlen($input); $i++) {
			if (!in_array($input[$i], self::TOKEN_CODEPOINT, true)) {
				return false;
			}
		}
		return true;
	}

	public static function decode(string $input): ?static {
		// https://mimesniff.spec.whatwg.org/#parsing-a-mime-type
		// 4.4.1: Remove any leading and trailing HTTP whitespace from input.
		$input = trim($input, "\n\r\t ");
		// 4.4.2: Let position be a position variable for input, initially pointing at the start of input.
		$position = 0;
		// 4.4.3: Let type be the result of collecting a sequence of code points that are not U+002F (/) from input, given position.
		$type = '';
		for (; $position < strlen($input) && $input[$position] !== '/'; $position++) {
			$type .= $input[$position];
		}
		// 4.4.4: If type is the empty string or does not solely contain HTTP token code points, then return failure.
		if (strlen($type) === 0 || !static::isHttpToken($type)) return null;
		// 4.4.5: If position is past the end of input, then return failure.
		if ($position >= strlen($input)) return null;
		// 4.4.6: Advance position by 1. (This skips past U+002F (/).)
		$position++;
		// 4.4.7: Let subtype be the result of collecting a sequence of code points that are not U+003B (;) from input, given position.
		$subtype = '';
		for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {
			$subtype .= $input[$position];
		}
		// 4.4.8: Remove any trailing HTTP whitespace from subtype.
		$subtype = rtrim($subtype, "\n\r\t ");
		// 4.4.9: If subtype is the empty string or does not solely contain HTTP token code points, then return failure.
		if (strlen($subtype) === 0 || !static::isHttpToken($subtype)) return null;
		// 4.4.10: Let mimeType be a new MIME type record whose type is type, in ASCII lowercase, and subtype is subtype, in ASCII lowercase.
		$mime_type = new static(strtolower($type), strtolower($subtype));
		// 4.4.11: While position is not past the end of input:
		while ($position < strlen($input)) {
			// 4.4.11.1: Advance position by 1. (This skips past U+003B (;).)
			$position++;
			// 4.4.11.2: Collect a sequence of code points that are HTTP whitespace from input given position.
			// This is roughly equivalent to skip ASCII whitespace, except that HTTP whitespace is used rather than ASCII whitespace.
			for (; $position < strlen($input) && (
				$input[$position] === "\n" ||
				$input[$position] === "\r" ||
				$input[$position] === "\t" ||
				$input[$position] === " "); $position++) {}
			// 4.4.11.3: Let parameterName be the result of collecting a sequence of code points that are not U+003B (;) or U+003D (=) from input, given position.
			$parameter_name = '';
			for (; $position < strlen($input) && $input[$position] !== ';' && $input[$position] !== '='; $position++) {
				$parameter_name .= $input[$position];
			}
			// 4.4.11.4: Set parameterName to parameterName, in ASCII lowercase.
			$parameter_name = strtolower($parameter_name);
			// 4.4.11.5: If position is not past the end of input, then:
			if ($position < strlen($input)) {
				// 4.4.11.5.1: If the code point at position within input is U+003B (;), then continue.
				if ($input[$position] === ';') continue;
				// 4.4.11.5.2: Advance position by 1. (This skips past U+003D (=).)
				$position++;
			}
			// 4.4.11.6: If position is past the end of input, then break.
			if ($position >= strlen($input)) break;
			// 4.4.11.7: Let parameterValue be null.
			$parameter_value = null;
			// 4.4.11.8: If the code point at position within input is U+0022 ("), then:
			if ($input[$position] === '"') {
				// 4.4.11.8.1: Set parameterValue to the result of collecting an HTTP quoted string from input, given position and the extract-value flag.
				$parameter_value = QuotedString::extract($input, $position, true);
				// 4.4.11.8.2: Collect a sequence of code points that are not U+003B (;) from input, given position.
				// Given text/html;charset="shift_jis"iso-2022-jp you end up with text/html;charset=shift_jis.
				for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {}
			}
			// 4.4.11.9: Otherwise:
			else {
				// 4.4.11.9.1: Set parameterValue to the result of collecting a sequence of code points that are not U+003B (;) from input, given position.
				$parameter_value = '';
				for (; $position < strlen($input) && $input[$position] !== ';'; $position++) {
					$parameter_value .= $input[$position];
				}
				// 4.4.11.9.2: Remove any trailing HTTP whitespace from parameterValue.
				$parameter_value = rtrim($parameter_value, "\n\r\t ");
				// 4.4.11.9.3: If parameterValue is the empty string, then continue.
				if (strlen($parameter_value) === 0) continue;
			}

			// 4.4.11.10: If all of the following are true then set mimeType’s parameters[parameterName] to parameterValue.
			if (
				// parameterName is not the empty string
				$parameter_value !== null && strlen($parameter_value) !== 0 &&
				// parameterName solely contains HTTP token code points
				static::isHttpToken($parameter_name) &&
				// parameterValue solely contains HTTP quoted-string token code points
				QuotedString::isValid($parameter_value, true) &&
				// mimeType’s parameters[parameterName] does not exist
				!array_key_exists($parameter_name, $mime_type->parameters)
			) {
				$mime_type->parameters[$parameter_name] = $parameter_value;
			}
		}

		// 4.4.12: Return mimeType.
		return $mime_type;
	}

	public function encode(): string {
		// https://mimesniff.spec.whatwg.org/#serializing-a-mime-type
		// 4.5.1: Let serialization be the concatenation of mimeType’s type, U+002F (/), and mimeType’s subtype.
		$serialization = $this->getType();
		// 4.5.2: For each name → value of mimeType’s parameters:
		foreach ($this->parameters as $name => $value) {
			// 4.5.2.1. Append U+003B (;) to serialization.
			$serialization .= ';';
			// 4.5.2.2: Append name to serialization.
			$serialization .= $name;
			// 4.5.2.3: Append U+003D (=) to serialization.
			$serialization .= '=';
			// 4.5.2.4: If value does not solely contain HTTP token code points or value is the empty string, then:
			if (strlen($value) === 0 || !static::isHttpToken($value)) {
				// 4.5.2.4.1: Precede each occurence of U+0022 (") or U+005C (\) in value with U+005C (\).
				// 4.5.2.4.2: Prepend U+0022 (") to value.
				// 4.5.2.4.3: Append U+0022 (") to value.
				$value = QuotedString::encode($value, true);
			}
			// 4.5.2.5: Append value to serialization.
			$serialization .= $value;
		}
		// 4.5.3: Return serialization.
		return $serialization;
	}

	public function setType(string $type, ?string $subtype = null): static {
		$parts = explode('/', $type, 2);
		$this->type = strtolower(ltrim($parts[0], "\n\r\t "));
		if (count($parts) > 1) {
			$this->subtype = strtolower(rtrim($parts[1], "\n\r\t "));
		} else {
			$this->subtype = '';
		}
		if ($subtype !== null) {
			$this->subtype = strtolower(rtrim($subtype, "\n\r\t "));
		}
		return $this;
	}

	public function getType(): string {
		return $this->type . '/' . $this->subtype;
	}

	public function getParameter(string $name): ?string {
		return array_key_exists($name, $this->parameters) ? $this->parameters[$name] : null;
	}

	public function setParameter(string $name, ?string $value = null): static {
		if ($value === null) {
			unset($this->parameters[$name]);
		} else {
			$this->parameters[$name] = $value;
		}
		return $this;
	}

	public function matches(Mime|string $type = '*', string $subtype = '*', bool $exact = false) {
		if (is_string($type)) {
			$parts = explode('/', $type, 2);
			if (count($parts) > 1 && $subtype === '*') {
				$subtype = $parts[1];
			}
			$type = $parts[0];
		} else {
			if (!empty($type->subtype) && $subtype === '*') {
				$subtype = $type->subtype;
			}
			$type = $type->type;
		}
		$type = strtolower(ltrim($type, "\n\r\t "));
		$subtype = strtolower(rtrim($subtype, "\n\r\t "));
		return (!$exact && ($type === '*' || $this->type === '*') || $type === $this->type) &&
			(!$exact && ($subtype === '*' || $this->subtype === '*') || $subtype === $this->subtype);
	}
}
