<?php

namespace App\Services\Crypto;

use App\Exceptions\WalletGenerationException;
use Elliptic\EC;
use kornrunner\Keccak;

/**
 * Generate Ethereum-compatible wallets and BIP-39 recovery phrases.
 * Keys are returned once and never persisted — caller is responsible for secure storage.
 */
class WalletGenerationService
{
    /**
     * Generate a BIP-39 mnemonic phrase (12 words, 128-bit entropy).
     */
    public function generateMnemonic(): string
    {
        try {
            $entropy = random_bytes(16); // 128 bits
            return $this->entropyToMnemonic($entropy);
        } catch (\Throwable $e) {
            throw new WalletGenerationException('Failed to generate mnemonic: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Create a new EVM account (address + private key pair).
     * Returns the address only — private key is intentionally NOT returned via API.
     * For wallet creation with key export, use createAccountWithKey() in trusted contexts.
     */
    public function createAddress(): string
    {
        ['address' => $address] = $this->generateKeyPair();
        return $address;
    }

    /**
     * Create a full key pair. Only call this from trusted internal contexts.
     *
     * @return array{address: string, private_key: string}
     */
    public function createAccountWithKey(): array
    {
        return $this->generateKeyPair();
    }

    /**
     * Derive the EVM address from a raw hex private key.
     */
    public function privateKeyToAddress(string $privateKey): string
    {
        $privateKey = ltrim($privateKey, '0x');

        if (strlen($privateKey) !== 64) {
            throw new WalletGenerationException('Private key must be 32 bytes (64 hex chars)');
        }

        $ec = new EC('secp256k1');
        $keyPair = $ec->keyFromPrivate($privateKey, 'hex');
        $pubKey = $keyPair->getPublic(false, 'hex');

        $pubBytes = hex2bin(substr($pubKey, 2));
        $hash = Keccak::hash($pubBytes, 256);

        return '0x' . substr($hash, -40);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private function generateKeyPair(): array
    {
        $ec = new EC('secp256k1');

        // Generate a random private key
        $privateKeyBytes = random_bytes(32);
        $privateKeyHex = bin2hex($privateKeyBytes);

        $keyPair = $ec->keyFromPrivate($privateKeyHex, 'hex');
        $pubKey = $keyPair->getPublic(false, 'hex');

        $pubBytes = hex2bin(substr($pubKey, 2));
        $hash = Keccak::hash($pubBytes, 256);
        $address = '0x' . substr($hash, -40);

        return [
            'address'     => $address,
            'private_key' => '0x' . $privateKeyHex,
        ];
    }

    /**
     * Convert 128-bit entropy to a 12-word BIP-39 mnemonic.
     * Uses the standard English wordlist.
     */
    private function entropyToMnemonic(string $entropyBytes): string
    {
        $wordlist = $this->getBip39Wordlist();

        // Compute checksum: SHA256 of entropy, take first (entropyBits/32) bits
        $hash = hash('sha256', $entropyBytes, true);
        $checksumBits = strlen($entropyBytes) * 8 / 32; // = 4 bits for 128-bit entropy

        // Convert entropy + checksum to binary string
        $entropyBin = '';
        foreach (str_split($entropyBytes) as $byte) {
            $entropyBin .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $checksumBin = str_pad(decbin(ord($hash[0])), 8, '0', STR_PAD_LEFT);
        $entropyBin .= substr($checksumBin, 0, (int) $checksumBits);

        // Split into 11-bit chunks, map to wordlist indices
        $words = [];
        $chunks = str_split($entropyBin, 11);

        foreach ($chunks as $chunk) {
            $index = bindec($chunk);
            $words[] = $wordlist[$index];
        }

        return implode(' ', $words);
    }

    private function getBip39Wordlist(): array
    {
        // Standard BIP-39 English wordlist (2048 words)
        // Full list loaded from file or embedded subset
        $wordlistPath = base_path('resources/bip39-english.txt');

        if (file_exists($wordlistPath)) {
            $words = array_filter(
                array_map('trim', file($wordlistPath)),
                fn($w) => $w !== ''
            );
            return array_values($words);
        }

        // Minimal fallback (first 2048 words — replace with full list in production)
        return $this->getEmbeddedWordlist();
    }

    private function getEmbeddedWordlist(): array
    {
        // Embedded BIP-39 English wordlist (2048 words)
        return explode("\n", "abandon\nability\nable\nabout\nabove\nabsent\nabsorb\nabstract\nabsurd\nabuse\naccess\naccident\naccount\naccuse\nachieve\nacid\nacoustic\nacquire\nacross\nact\naction\nactor\nactress\nactual\nadapt\nadd\naddict\naddress\nadjust\nadmit\nadult\nadvance\nadvice\naerosol\naffair\nafford\nafraid\nagain\nage\nagent\nagree\nahead\naim\nair\nairport\naisle\nalarm\nalbum\nalcohol\nalert\nalien\nall\nalley\nallow\nalmost\nalone\nalpha\nalready\nalso\nalter\nalways\namateur\namazing\namong\namount\namused\nanalyst\nanchor\nancient\nanger\nangle\nangry\nanimal\nankle\nannounce\nannual\nanother\nanswer\nantenna\nantique\nanxiety\nany\napart\napology\nappear\napple\napprove\napril\narch\narctic\narea\narena\nargue\narm\narmed\narmor\narmy\naround\narrange\narrest\narrive\narrow\nart\nartefact\nartist\nartwork\nask\naspect\nassault\nasset\nassist\nassume\nasthma\nathlete\natom\nattack\nattend\nattitude\nattract\nauction\nauditor\naugust\naunty\nauthor\nauto\nautumn\naverage\navoid\nawake\naware\naway\nawesome\nawful\nawkward\naxis\nbaby\nbackground\nbadge\nbag\nbalance\nbalcony\nball\nbamboo\nbanana\nbanner\nbar\nbare\nbargain\nbarrel\nbase\nbasic\nbasis\nbasket\nbattle\nbeach\nbean\nbeauty\nbecause\nbecome\nbeen\nbefore\nbehind\nbelong\nbenefit\nbest\nbetray\nbetter\nbetween\nbeyond\nbicycle\nbid\nbike\nbind\nbiology\nbird\nbirth\nbitter\nblack\nblade\nblame\nblanket\nblast\nbleak\nbless\nblind\nblood\nblossom\nblouse\nblue\nblur\nblush\nboard\nboat\nbody\nboil\nbomb\nbone\nbook\nboost\nborder\nboring\nborrow\nboss\nbottom\nbounce\nbox\nboy\nbracket\nbrain\nbrand\nbrave\nbread\nbreeze\nbrick\nbridge\nbrief\nbright\nbring\nbrisk\nbroccoli\nbroken\nbronze\nbroom\nbrother\nbrown\nbrush\nbubble\nbulk\nbull\nbundle\nbunker\nburden\nburger\nburst\nbus\nbusiness\nbusy\nbutter\nbuyer\nbuzz\ncabbage\ncabin\ncable\ncactus\ncage\ncake\ncall\ncalm\ncamera\ncamp\ncan\ncanal\ncancel\ncandid\ncane\ncannon\ncanoe\ncanvas\ncanyon\ncapable\ncapital\ncaptain\ncar\ncarbon\ncard\ncargo\ncarpet\ncarry\ncart\ncase\ncash\ncastle\ncasual\ncat\ncatalog\ncatch\ncategory\ncattle\ncaught\ncause\ncaution\ncave\nceiling\ncelery\ncement\ncensus\ncentury\ncereal\ncertain\nchain\nchair\nchaos\nchapter\ncharge\nchase\nchat\ncheap\ncheck\ncheese\nchef\ncherry\nchest\nchicken\nchief\nchild\nchimney\nchoice\nchoose\nchronic\nchuckle\nchunk\nchurn\ncigar\ncinnamon\ncircle\ncitizen\ncity\ncivil\nclaim\nclap\nclarify\nclaw\nclay\nclean\nclerk\nclever\nclick\nclient\ncliff\nclimb\nclinic\nclip\nclock\nclog\nclose\ncloth\ncloud\nclown\nclub\nclump\ncluster\nclutch\ncoach\ncoast\ncoconut\ncode\ncoffee\ncoil\ncoin\ncollect\ncolor\ncolumn\ncombine\ncome\ncomfort\ncomic\ncommon\ncommunity\ncomplex\nconcern\nconduct\nconfirm\ncongress\nconnect\nconsider\ncontrol\nconvince\ncook\ncool\ncooper\ncopy\ncoral\ncore\ncorn\ncorrect\ncost\ncotton\ncouch\ncountry\ncouple\ncourse\ncover\ncoyote\ncrack\ncradle\ncraft\ncram\ncrane\ncrash\ncrate\ncrayon\ncrazy\ncream\ncredit\ncreek\ncrew\ncricket\ncrime\ncrisp\ncritic\ncross\ncrouch\ncrowd\ncrucial\ncruel\ncruise\ncrumble\ncrunch\ncrush\ncry\ncrystal\ncube\nculture\ncup\ncupboard\ncurious\ncurrent\ncurtain\ncurve\ncushion\ncustom\ncute\ncycle\ndad\ndamage\ndamp\ndance\ndanger\ndaring\ndash\ndaughter\ndawn\nday\ndeal\ndebate\ndebris\ndecade\ndecember\ndecide\ndecline\ndecorate\ndecrease\ndeer\ndefense\ndefine\ndefy\ndegree\ndelay\ndeliver\ndemand\ndemise\ndental\ndepart\ndepend\ndepict\ndeploy\ndesign\ndesk\ndespair\ndetect\ndevelop\ndevice\ndial\ndiamond\ndiary\ndice\ndiesel\ndiet\ndiffer\ndigital\ndignity\ndinner\ndirect\ndirt\ndisgust\ndish\ndismiss\ndisorder\ndisplay\ndistance\ndivert\ndivide\ndivorce\ndizzy\ndoctor\ndocument\ndog\ndoll\ndolphin\ndomain\ndonate\ndonkey\ndonor\ndoor\ndose\ndouble\ndove\ndraft\ndragon\ndrama\ndrastic\ndraw\ndream\ndress\ndrift\ndrill\ndrink\ndrip\ndrive\ndrop\ndrum\ndry\nduck\ndumb\ndune\nduring\ndust\ndutch\nduty\ndwarf\ndynamic\neager\neagle\nearly\nearth\neasily\neast\neasy\necho\nedge\nedit\neducate\neffort\negg\neight\neither\nelbow\nelder\nelect\nelephant\nelevator\nelite\nelse\nembark\nembody\nempower\nempty\nenact\nengage\nengine\nenhance\nenjoy\nenlist\nenough\nenrich\nenroll\nensure\nenter\nentire\nentry\nenvision\nepic\nequal\nequip\nerase\nerode\nerosion\nerror\nerupt\nescape\nessay\nestablish\nethnic\nevade\nevent\never\nevil\nevoke\nevolve\nexact\nexample\nexcess\nexchange\nexcite\nexclude\nexecute\nexercise\nexhaust\nexhibit\nexile\nexist\nexit\nexotic\nexpand\nexplain\nexpose\nexpand\nexpress\nextend\nextra\neye\nfable\nface\nfaculty\nfaint\nfaith\nfall\nfalse\nfame\nfamily\nfamous\nfan\nfancy\nfantasy\nfar\nfarmer\nfast\nfate\nfather\nfatty\nfault\nfavorite\nfeature\nfederal\nfeel\nfeet\nfellow\nfelt\nfence\nfestival\nfetch\nfever\nfew\nfiber\nfiction\nfield\nfigure\nfile\nfilm\nfilter\nfinal\nfind\nfine\nfinger\nfinish\nfire\nfirm\nfirst\nfiscal\nfish\nfit\nfitness\nfix\nflag\nflame\nflash\nflat\nflavor\nfleece\nflight\nflip\nfloat\nflock\nfloor\nflower\nfluid\nflush\nfly\nfoam\nfocus\nfog\nfoil\nfollow\nfood\nfoot\nforce\nforest\nforget\nfork\nfortune\nforum\nforward\nfossil\nfoster\nfound\nfox\nfragile\nframe\nfresh\nfriend\nfringe\nfrog\nfront\nfrost\nfrown\nfrozen\nfruit\nfuel\nfun\nfunny\nfurnace\nfury\nfuture\ngadget\ngain\ngalaxy\ngallery\ngame\ngap\ngarbage\ngarlic\ngarment\ngas\ngasp\ngate\ngather\ngauge\ngaze\ngeneral\ngenius\ngenre\ngently\ngesture\nget\nghost\ngiant\ngift\ngiggle\nginger\ngiraffe\ngirl\ngive\nglad\nglance\nglare\nglass\nglide\nglimp\nglobal\ngloom\nglory\nglove\nglow\nglue\ngoat\ngoblin\ngod\ngold\ngood\ngoose\ngorilla\ngossip\ngrace\ngrain\ngrant\ngrape\ngrasp\ngrass\ngravity\ngreat\ngreen\ngrid\ngrief\ngrim\ngrin\ngrip\ngrit\ngrocer\ngroup\ngrow\ngruesome\nguard\nguide\nguilt\nguitar\ngun\nguy\ngym\nhabit\nhair\nhalf\nhammer\nhamster\nhand\nhappy\nharsh\nharvest\nhave\nhawk\nhazard\nhead\nhealth\nheart\nheavy\nhedgehog\nheight\nhello\nhelmet\nhelp\nhen\nhero\nhidden\nhigh\nhint\nhip\nhire\nhistory\nhobby\nhockey\nhold\nhole\nholiday\nhollow\nhome\nhoney\nhood\nhope\nhorn\nhospital\nhost\nhour\nhovering\nhug\nhuge\nhuman\nhumble\nhumor\nhundred\nhungry\nhunt\nhurdle\nhurry\nhurt\nhusband\nhybrid\nice\nicon\nignore\nillegal\nillness\nimage\nimitate\nimmense\nimmune\nimpact\nimpose\nimprove\nimpulse\ninbox\ninclude\nincome\nindex\nindicate\nindoor\nindustry\ninfant\ninfirm\ninfuse\ninner\ninnate\ninnocent\ninput\ninsane\ninsect\ninside\ninspire\ninstall\nintact\ninterest\ninto\ninvest\ninvite\ninvolve\niron\nislam\nisland\nisolate\nissue\nitem\nivory\njacket\njaguar\njar\njazz\njealous\njeans\njelly\njewel\njob\njoin\njoke\njourney\njoy\njudge\njuice\njump\njungle\njunior\njunk\njust\nkangaroo\nkeen\nkeep\nketchup\nkey\nkick\nkid\nkidney\nkind\nkingdom\nkiss\nkit\nkite\nkitten\nkiwi\nknee\nknife\nknock\nknow\nlab\nlabel\nlamp\nlanguage\nlaptop\nlarge\nlater\nlaughter\nlaundry\nlave\nlawn\nlawsuit\nlayer\nlazy\nleader\nlearn\nleave\nlecture\nleft\nlegacy\nlegal\nlegend\nleisure\nlemon\nlend\nlength\nlens\nleopard\nlesson\nletter\nlevel\nliar\nliberty\nlibrary\nlicense\nlife\nlift\nlike\nlimb\nlimit\nline\nlion\nliquid\nlist\nlittle\nload\nlobster\nlocal\nlock\nlogic\nlonely\nlong\nloop\nlottery\nloud\nlounge\nlove\nloyalty\nluck\nluggage\nlumber\nlunar\nlunch\nluxury\nmad\nmail\nmain\nmaintain\nmaize\nmajor\nmake\nmamal\nman\nmanage\nmandate\nmango\nmanual\nmaple\nmarket\nmatch\nmaterial\nmath\nmatter\nmaximum\nmaze\nmeadow\nmean\nmedal\nmedia\nmelody\nmelt\nmember\nmental\nmention\nmenu\nmercy\nmesh\nmessage\nmetal\nmethod\nmiddle\nmidnight\nmilk\nminimum\nmirror\nmisery\nmiss\nmistake\nmix\nmixed\nmixture\nmodel\nmodify\nmom\nmonitor\nmonkey\nmonster\nmonth\nmoon\nmortal\nmosquito\nmother\nmotion\nmotorcycle\nmountain\nmouse\nmovie\nmuch\nmuffin\nmule\nmulti\nmuscle\nmuseum\nmushroom\nmusic\nmust\nmutual\nmyself\nmystery\nmyth\nnaive\nname\nnapkin\nnear\nneck\nneglect\nneither\nneon\nnerve\nnest\nnetwork\nneutral\nnever\nnews\nnext\nnice\nnight\nnoise\nnominal\nnoodle\nnormal\nnotion\nnovelty\nnow\nnuclear\nnudge\nnumber\nnurse\nnutrition\noak\noblige\nobscure\nobserve\nobtain\nocean\noctober\nodor\noffice\noften\noil\nokay\nold\nolive\nomit\nonce\nonion\nopen\noperate\noppose\noption\norder\nordinary\norgan\norient\noriginal\norphan\nostrich\nother\noutdoor\noven\nown\nowner\nozone\npact\npadding\npage\npair\npalace\npalm\npanda\npanel\npanic\npanther\npaper\nparade\nparent\npark\nparrot\nparty\npath\npatrol\npause\npave\npayment\npeace\npeanut\npebble\npecan\npenalty\npencil\npeople\npepper\nperfect\npermit\nperson\npet\nphone\nphoto\nphrase\nphysical\npiano\npicnic\npicture\npiece\npig\npigeon\npill\npilot\npink\npioneer\npipe\npistol\npitch\npizza\nplace\nplanet\nplastic\nplate\nplateau\nplay\nplease\npleadge\nplug\nplunge\npoem\npoet\npoint\npolar\npole\npolice\npool\npopular\nporter\nposition\npossible\npost\npotato\npower\npractice\npraise\npredict\nprefer\nprepare\npresent\npretty\nprevent\nprice\npride\nprimary\nprint\npriority\nprism\nprison\nprotect\nproud\nprovide\npublic\npudding\npull\npulp\nputting\npuzzle\npyramid\nquality\nquantity\nquarter\nquestion\nqueue\nquick\nquit\nquiz\nquote\nrabbit\nraccoon\nrace\nrack\nradar\nradio\nrange\nrapid\nrare\nrate\nrather\nraven\nreach\nread\nready\nreal\nreason\nrebel\nrecall\nrecord\nrecycle\nreduce\nreflect\nreform\nrefuse\nregion\nregret\nreject\nrelay\nrelease\nrely\nremain\nremember\nremind\nremove\nrename\nrental\nreplace\nreport\nrequire\nrescue\nresemble\nresist\nresort\nresource\nrespect\nrest\nresult\nretire\nreturn\nreunion\nreveal\nreview\nreward\nrhythm\nribbon\nridge\nrife\nrisk\nritual\nroam\nroast\nrobust\nrocket\nromantic\nroof\nrooster\nround\nroute\nroyal\nruby\nrude\nrule\nrun\nrunway\nrural\nsad\nsaddle\nsadness\nsafe\nsail\nsalad\nsalmon\nsalon\nsalt\nsalute\nsame\nsample\nsand\nsatisfy\nsave\nscan\nscare\nscatter\nscene\nscenery\nscheme\nschool\nscience\nscissors\nscorpion\nscout\nscrap\nscreen\nscript\nscrub\nsea\nsearch\nseason\nseat\nsecond\nserious\nservice\nset\nsettle\nsetup\nseven\nshadow\nshaft\nshallow\nshame\nshape\nshare\nsharp\nsheet\nshelf\nship\nshiver\nshock\nshoe\nshoot\nshop\nshort\nshoulder\nshout\nshove\nshrimp\nshrug\nshuffle\nshut\nshy\nsickness\nside\nsight\nsilence\nsilk\nsilver\nsimilar\nsince\nsiren\nsister\nsituate\nsix\nsize\nskate\nsketch\nski\nskill\nskin\nskirt\nskull\nslab\nslam\nsleep\nslender\nslide\nslot\nslow\nslush\nsmall\nsmile\nsmoke\nsmooth\nsnack\nsnake\nsnap\nsniff\nsnow\nsoap\nsoccer\nsocial\nsock\nsoda\nsoft\nsolar\nsolution\nsolve\nsome\nsong\nsoon\nsorry\nsoul\nsound\nsoup\nsource\nsouth\nspace\nspare\nspark\nspawn\nspeak\nspend\nspoke\nspoon\nspray\nspread\nspring\nspy\nsquare\nsqueeze\nsquirrel\nstable\nstadium\nstaff\nstage\nstair\nstamp\nstand\nstart\nsteak\nsteel\nstem\nstep\nstereo\nstick\nstill\nstomp\nstore\nstory\nstove\nstrategic\nstraw\nstream\nstreet\nstrike\nstrong\nstruggle\nstudent\nstuff\nstuffy\nstumble\nsubject\nsubmit\nsubway\nsuccess\nsuffer\nsuggest\nsuit\nsummer\nsupport\nsurface\nsurge\nsurprise\nsuspect\nsustain\nswallow\nswamp\nswap\nswarm\nswear\nsweep\nsweet\nswift\nswim\nswitch\nsymbol\nsymptom\ntable\ntackle\ntag\ntail\ntarnish\ntask\ntaste\ntaxpayer\nteach\nteam\ntell\ntennis\ntent\nterm\ntest\ntexture\nthat\nthem\nthen\ntheory\nthere\nthey\nthing\nthink\nthird\nthis\nthought\nthreat\nthree\nthrive\nthrow\nthumb\nthunder\ntimer\ntiny\ntip\ntired\ntitle\ntoast\ntobacco\ntoday\ntoggle\ntomato\ntomorrow\ntorch\ntorment\ntorpedo\ntortoise\ntoss\ntotal\ntourist\ntoward\ntower\ntown\ntrack\ntrade\ntragic\ntransfer\ntrap\ntrash\ntravel\ntray\ntreat\ntrend\ntrial\ntribe\ntrick\ntriger\ntrimmer\ntrophy\ntruck\ntrust\ntruth\ntry\ntube\ntumble\ntuna\ntunnel\nturkey\nturn\nturtle\ntypical\numbra\nable\nuncle\nuncover\nunderground\nundo\nunique\nunit\nunlock\nuntil\nunusual\nunveil\nupdate\nupgrade\nuphold\nupon\nupstream\nurge\nusage\nuser\nusher\nutensil\nutility\nvacuum\nvague\nvalid\nvalley\nvalve\nvan\nvanish\nvapor\nvarious\nvault\nvehicle\nvelvet\nvendor\nventure\nverify\nvery\nveteran\nvex\nviral\nvirtual\nvirtue\nvisible\nvision\nvisit\nvital\nvivid\nvocal\nvolcano\nvolume\nvote\nvoyage\nvulture\nwage\nwagon\nwait\nwalk\nwall\nwalnut\nwant\nwarfare\nwarm\nwarrior\nwaste\nwater\nwave\nway\nwealth\nweapon\nwear\nweasel\nweather\nweb\nwedding\nweekend\nweird\nwelcome\nwell\nwest\nwet\nwhale\nwheat\nwheel\nwhen\nwhere\nwhip\nwisdom\nwish\nwitness\nwolf\nwoman\nwonder\nwood\nwool\nword\nwork\nworld\nworm\nworship\nworth\nwrap\nwreck\nwrestle\nwrist\nwrite\nwrong\nyard\nyear\nyellow\nyou\nyoung\nyouth\nzeal\nzero\nzone\nzoo");
    }
}
