<?php
/**
 * Road Map to Jesus: Statement of Faith page, and corrections to the Visit page wording
 * (Evangelist Godstime Adah, 1 Thessalonians 5:17, visit@roadmaptojesus.org for all visitors).
 *
 * @package KingdomSaaS
 */

defined( 'ABSPATH' ) || exit;

$beliefs = array(
	'The Holy Spirit'                        => 'The Holy Spirit created the universe with the Father and Son. The Father sent His Spirit to create us like Jesus Christ. The Jesus we know is Jesus in Spirit-filled power. In John 14:16-17, I will ask the Father, and He will give you another Counselor to be with you forever — the Spirit of Truth. The world can’t accept Him because it doesn’t see Him. But you know Him because He dwells in you. The Holy Spirit never leaves us. People who are prepared for Him know and accept Him. The Holy Spirit reveals our sins. He aids us in accepting Jesus Christ as Savior. He alters our lives forever. This is known as rebirth.',
	'Jesus Christ saves souls'               => 'That’s why He came, lived, died, and rose again. He came to reestablish God-man fellowship. Jesus Christ was a descendant of David in human nature. His divine essence was revealed when He was risen from the dead on the third day. He now sits at God’s right hand (Hebrews 12:2; Romans 1:2-4). He was tempted as we are, but without sin. Jesus Christ loves us, died for us, and continues to pray for us.',
	'The Bible'                              => 'The Holy Spirit carried holy men of God as they conveyed God’s word. The Bible is more than ancient events and wisdom. His message of love and truth today. (2 Timothy 3:16; 2 Peter 1:21).',
	'Sin and eternal life'                   => 'Sin leads to everlasting death, while God’s Word leads to life. If Jesus is our Lord and Savior, we will have a new body, soul, and spirit. God’s Spirit joins our spirit to proclaim us God’s offspring (Romans 8:16).',
	'Salvation'                              => 'Salvation is obtained through believing in the purifying power of Jesus Christ’s Blood. Jesus’ death will not redeem a man until he accepts Jesus as his personal Lord and Savior.',
	'God’s Word refreshes the mind and regenerates the spirit' => 'To be born again, we need God’s Word, His Spirit, repentance, and trust.',
	'Divine healing'                         => 'Divine healing is God’s miraculous ability to heal the body. It is received through trust in Christ’s completed work. Christ’s suffering before and after His crucifixion was for our spiritual, soul and physical recovery. His stripes heal us. Divine healing was among the privileges purchased by Jesus Christ at Calvary.',
	'Baptism with water and the Holy Spirit' => 'We believe in speaking in tongues as the Holy Spirit utters (Acts 2:4). All who join Christ’s body do so because they are Spirit-baptized (1 Corinthians 12:13; Romans 8:9). In the same way that God’s power came upon the first disciples on Pentecost (Acts 2:1-4). When God’s power descends upon you, the Holy Spirit will transform you. The Holy Spirit causes rivers of life, joy, love, peace, and power to pour from your spirit to others (John 7:37-38).',
	'The Lord’s Supper'                      => 'In Matthew 26:26-28, Jesus and His followers celebrated the Lord’s Supper. ‘Take and eat; this is My body’, Jesus said to His followers as they were eating. Then He took the cup, thanked them, and said, ‘Drink from it, all of you. This is My covenant blood, poured forth for many to be forgiven.’ As Jesus Christ taught His followers to partake of the Lord’s Supper, so we participate (2 Peter 1:4) upon the Holy Spirit’s guidance (1 Corinthians 2:10; 11:26-31).',
	'The return of Jesus Christ'             => 'Jesus Christ will return, precisely as He did when He first appeared (Acts 1:11; 1 Thessalonians 4:16-17).',
);
$html = "<h2>Our Beliefs</h2>\n";
foreach ( $beliefs as $heading => $text ) {
	$html .= '<h3>' . esc_html( $heading ) . "</h3>\n<p>" . esc_html( $text ) . "</p>\n";
}

return array(
	'match'        => 'road map to jesus',
	'regex'        => array(
		'kingdom_vp_intro' => array( '/\bGodstime Ada\b/', 'Godstime Adah' ),
		'kingdom_vp_faq'   => array( array( '/\bGodstime Ada\b/', '/\bI Thessalonians 5:16\b/', '/info@roadmaptojesus\.org/' ), array( 'Godstime Adah', '1 Thessalonians 5:17', 'visit@roadmaptojesus.org' ) ),
	),
	'beliefs_page' => array( 'Statement of Faith', 'statement-of-faith', $html ),
);
