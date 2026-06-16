// Data for pro wrestlers including their finishing moves
        const wrestlersData = [
            {
                name: "AJ Styles",
                height: "5'11\"",
                weight: 218,
                description: "The Phenomenal One - One of the most technically gifted wrestlers of his generation. Two-time WWE Champion, former TNA World Heavyweight Champion, and IWGP Heavyweight Champion. Known for his incredible in-ring ability and signature moves like the Styles Clash and Phenomenal Forearm. Helped elevate TNA Wrestling during its peak years and brought legitimacy to WWE's acquisition of indie talent.",
                baseHp: 100,
                strength: 74,
                technicalAbility: 96,
                brawlingAbility: 81,
                stamina: 90,
                aerialAbility: 90,
                toughness: 81,
                moves: {
                    grapple: { name: "Phenomenal Forearm", damage: { min: 15, max: 18 }, baseHitChance: 0.8, stat: 'aerialAbility' },
                    strike: { name: "Spiral Tap", damage: { min: 14, max: 17 }, baseHitChance: 0.78, stat: 'aerialAbility' },
                    highFlying: { name: "Calf Crusher", damage: { min: 12, max: 14 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    finisher: { name: "Styles Clash", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Andre the Giant",
                height: "7'4\"",
                weight: 520,
                description: "The Eighth Wonder of the World. Standing 7'4\" and weighing over 500 pounds, Andre was professional wrestling's most recognizable star worldwide. Famous for his undefeated streak that lasted over a decade, his match with Hulk Hogan at WrestleMania III drew over 93,000 fans. Beyond wrestling, he appeared in films like \"The Princess Bride.\" His physical presence and gentle personality made him a global ambassador for the sport.",
                baseHp: 110,
                strength: 98,
                technicalAbility: 40,
                brawlingAbility: 97,
                stamina: 68,
                aerialAbility: 2,
                toughness: 95,
                moves: {
                    grapple: { name: "Bodyslam", damage: { min: 15, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    strike: { name: "Headbutt", damage: { min: 12, max: 16 }, baseHitChance: 0.75, stat: 'strength' },
                    highFlying: { name: "Big Boot", damage: { min: 10, max: 16 }, baseHitChance: 0.6, stat: 'strength' },
                    finisher: { name: "Sit-down Splash", damage: { min: 28, max: 40 }, baseHitChance: 0.85, stat: 'strength' }
                }
            },
            {
                name: "Arn Anderson",
                height: "6'1\"",
                weight: 249,
                description: "The Enforcer. Master of fundamental wrestling and psychology. Key member of The Four Horsemen alongside Ric Flair. Never held a world championship but was considered one of the best wrestlers never to do so. Exceptional storyteller in the ring and on the microphone. Later became a respected backstage producer and trainer, helping develop future stars.",
                baseHp: 100,
                strength: 87,
                technicalAbility: 92,
                brawlingAbility: 85,
                stamina: 92,
                aerialAbility: 21,
                toughness: 89,
                moves: {
                    grapple: { name: "Spinebuster", damage: { min: 13, max: 19 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Gourdbuster", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    highFlying: { name: "DDT", damage: { min: 9, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Double A Spinebuster", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Barbarian",
                height: "6'2\"",
                weight: 280,
                description: "The Barbarian. Powerful wrestler known for his headbutt finishing move and his partnership with The Warlord as The Powers of Pain. Standing 6'2\" and weighing over 280 pounds of muscle, he was legitimately one of wrestling's strongest performers. Had successful runs in both WWF and WCW, often portrayed as a savage or monster character. His kick-out power was legendary, often requiring multiple finishers to keep him down. Part of Heenan Family and later formed successful tag teams. Known for his professionalism and longevity, competing effectively well into his 40s while maintaining his impressive physique.",
                baseHp: 105,
                strength: 95,
                technicalAbility: 65,
                brawlingAbility: 96,
                stamina: 90,
                aerialAbility: 48,
                toughness: 98,
                moves: {
                    grapple: { name: "Powerslam", damage: { min: 10, max: 17 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "Big Boot", damage: { min: 12, max: 16 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    highFlying: { name: "Bearhug", damage: { min: 10, max: 14 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    finisher: { name: "Top Rope Headbutt", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'aerialAbility' }
                }
            },
            {
                name: "Barry Windham",
                height: "6'6\"",
                weight: 275,
                description: "Son of wrestling legend Blackjack Mulligan, Barry was a gifted technical wrestler with remarkable athleticism for his size. Multiple-time tag team champion and singles titleholder across various promotions. Member of The Four Horsemen and known for his smooth in-ring style. Considered one of the most naturally talented wrestlers who perhaps didn't reach his full potential due to various circumstances.",
                baseHp: 105,
                strength: 88,
                technicalAbility: 95,
                brawlingAbility: 89,
                stamina: 100,
                aerialAbility: 54,
                toughness: 93,
                moves: {
                    grapple: { name: "Lariat", damage: { min: 12, max: 17 }, baseHitChance: 0.88, stat: 'brawlingAbility' },
                    strike: { name: "Flying Clothesline", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Bulldog", damage: { min: 11, max: 14 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    finisher: { name: "Superplex", damage: { min: 26, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Batista",
                height: "6'6\"",
                weight: 290,
                description: "The Animal. Six-time World Champion (4-time World Heavyweight Champion, 2-time WWE Champion). Original member of Evolution alongside Triple H and Ric Flair. Known for his incredible physique and power moves like the Batista Bomb. Successfully transitioned to Hollywood, starring in \"Guardians of the Galaxy\" and other major films. His 2005 face turn and title win was one of WWE's most successful storylines.",
                baseHp: 100,
                strength: 96,
                technicalAbility: 67,
                brawlingAbility: 92,
                stamina: 89,
                aerialAbility: 30,
                toughness: 92,
                moves: {
                    grapple: { name: "Spear", damage: { min: 13, max: 18 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "Spinebuster", damage: { min: 11, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    highFlying: { name: "Full Nelson Slam", damage: { min: 12, max: 17 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Batista Bomb", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Big Boss Man",
                height: "6'6\"",
                weight: 330,
                description: "Ray Traylor brought legitimacy to the \"corrections officer\" gimmick with his background in law enforcement. Surprisingly agile for his size, known for his sidewalk slam finisher. Had memorable feuds with Hulk Hogan and formed an unlikely tag team with Ken Shamrock. His character work and ability to be both heel and face effectively made him a valuable mid-card performer throughout the 1990s.",
                baseHp: 100,
                strength: 92,
                technicalAbility: 70,
                brawlingAbility: 89,
                stamina: 84,
                aerialAbility: 20,
                toughness: 91,
                moves: {
                    grapple: { name: "Sidewalk Slam", damage: { min: 12, max: 16 }, baseHitChance: 0.75, stat: 'strength' },
                    strike: { name: "Pendulum Backbreaker", damage: { min: 10, max: 18 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Big Boot", damage: { min: 9, max: 15 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    finisher: { name: "Boss Man Slam", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Big John Studd",
                height: "6'10\"",
                weight: 364,
                description: "Standing 6'10\" and weighing over 360 pounds, Studd was one of the premier giants of the 1980s WWF. Won the first-ever Royal Rumble in 1989. Famous for his bodyslam challenges and feuds with Andre the Giant. His matches helped establish the spectacle of giant vs. giant encounters that became a wrestling staple.",
                baseHp: 100,
                strength: 94,
                technicalAbility: 55,
                brawlingAbility: 87,
                stamina: 75,
                aerialAbility: 10,
                toughness: 86,
                moves: {
                    grapple: { name: "Double Underhook Suplex", damage: { min: 12, max: 17 }, baseHitChance: 0.8, stat: 'strength' },
                    strike: { name: "Big Boot", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    highFlying: { name: "Backbreaker", damage: { min: 11, max: 16 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Reverse Bear Hug", damage: { min: 28, max: 34 }, baseHitChance: 0.85, stat: 'strength' }
                }
            },
            {
                name: "Bob Backlund",
                height: "6'1\"",
                weight: 245,
                description: "Mr. Bob Backlund. WWE Champion for over 5 years (1978-1983), one of the longest reigns in company history. Known for his amateur wrestling background and technical prowess. His 1994 heel turn as a deranged former champion was one of wrestling's most effective character transformations. Helped bridge the gap between the territorial era and national expansion of wrestling.",
                baseHp: 105,
                strength: 87,
                technicalAbility: 97,
                brawlingAbility: 83,
                stamina: 95,
                aerialAbility: 45,
                toughness: 93,
                moves: {
                    grapple: { name: "Atomic Knee Drop", damage: { min: 10, max: 15 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    strike: { name: "Belly-to-back Rolling Bridge", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    highFlying: { name: "Atomic Spinecrusher", damage: { min: 11, max: 17 }, baseHitChance: 0.65, stat: 'technicalAbility' },
                    finisher: { name: "Crossface Chickenwing", damage: { min: 25, max: 38 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Bobby Eaton",
                height: "5'10\"",
                weight: 230,
                description: "Beautiful Bobby. Before setting out as a singles wrestler, was one half of The Midnight Express with Dennis Condrey and later Stan Lane, considered one of the greatest tag teams in wrestling history. Known for his technical wrestling ability and his Alabama Jam (top rope legdrop) finishing move. Multiple-time NWA World Tag Team Champion whose matches with The Rock 'n' Roll Express were classics of tag team wrestling. His work with manager Jim Cornette helped establish the heel manager dynamic that influenced wrestling for decades. Respected as one of the most underrated technical wrestlers who could work with anyone and make them look good.",
                baseHp: 100,
                strength: 75,
                technicalAbility: 92,
                brawlingAbility: 86,
                stamina: 90,
                aerialAbility: 91,
                toughness: 87,
                moves: {
                    grapple: { name: "Spinning Neckbreaker", damage: { min: 10, max: 17 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "Armbar DDT", damage: { min: 12, max: 14 }, baseHitChance: 0.85, stat: 'technicalAbility' },
                    highFlying: { name: "Diving Knee Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Alabama Jam", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'aerialAbility' }
                }
            },
            {
                name: "Bobby Lashley",
                height: "6'3\"",
                weight: 273,
                description: "The All Mighty.  Former U.S. Army veteran who became WWE Champion and ECW Champion. Known for his incredible physique and legitimate amateur wrestling background. Successfully competed in mixed martial arts, proving his athletic credibility. His later career renaissance, particularly his work with MVP and The Hurt Business, showcased his improved mic skills and character development.",
                baseHp: 100,
                strength: 96,
                technicalAbility: 80,
                brawlingAbility: 92,
                stamina: 90,
                aerialAbility: 37,
                toughness: 93,
                moves: {
                    grapple: { name: "Spear", damage: { min: 13, max: 18 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "Dominator", damage: { min: 10, max: 17 }, baseHitChance: 0.7, stat: 'strength' },
                    highFlying: { name: "Full Nelson Slam", damage: { min: 12, max: 16 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Hurt Lock", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Booker T",
                height: "6'3\"",
                weight: 256,
                description: "Can you dig it, sucka?! Five-time WCW World Heavyweight Champion and one-time World Heavyweight Champion in WWE. Master of the spinaroonie and Book End finisher. Successful tag team career with his brother Stevie Ray as Harlem Heat. Later became a respected commentator and trainer. His charisma and catchphrases made him one of the most entertaining performers of his era.",
                baseHp: 100,
                strength: 87,
                technicalAbility: 85,
                brawlingAbility: 92,
                stamina: 89,
                aerialAbility: 80,
                toughness: 84,
                moves: {
                    grapple: { name: "Book End", damage: { min: 13, max: 19 }, baseHitChance: 0.78, stat: 'strength' },
                    strike: { name: "Houston Hangover", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Missile Dropkick", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    finisher: { name: "Scissors Kick", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Braun Strowman",
                height: "6'8\"",
                weight: 385,
                description: "The Monster Among Men. Standing 6'8\" and weighing over 380 pounds, Strowman became known for incredible feats of strength and his catchphrase \"Get these hands!\" Former Universal Champion and multiple-time tag team champion. His feuds with Roman Reigns and Brock Lesnar were highlights of WWE programming. Started as a member of The Wyatt Family before becoming a fan-favorite monster face.",
                baseHp: 105,
                strength: 98,
                technicalAbility: 60,
                brawlingAbility: 92,
                stamina: 86,
                aerialAbility: 20,
                toughness: 90,
                moves: {
                    grapple: { name: "Fallaway Slam", damage: { min: 12, max: 18 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Backbreaker", damage: { min: 11, max: 17 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    highFlying: { name: "Big Boot", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Running Powerslam", damage: { min: 30, max: 40 }, baseHitChance: 0.92, stat: 'strength' }
                }
            },
            {
                name: "Bray Wyatt",
                height: "6'3\"",
                weight: 285,
                description: "The Eater of Worlds/The Fiend. Mysterious, psychological, and dangerous.",
                baseHp: getRandomInt(95, 110),
                strength: getRandomInt(80, 95),
                technicalAbility: getRandomInt(60, 80),
                brawlingAbility: getRandomInt(85, 100),
                stamina: getRandomInt(75, 90),
                aerialAbility: getRandomInt(20, 40),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Sister Abigail", damage: { min: 15, max: 22 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    strike: { name: "Mandible Claw", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Senton", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Sister Abigail", damage: { min: 28, max: 38 }, baseHitChance: 0.92, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Bret Hart",
                height: "6'0\"",
                weight: 235,
                description: "\"The Excellence of Execution\" - Five-time WWE Champion and considered one of the greatest technical wrestlers ever. Member of the legendary Hart wrestling family from Calgary. His matches told perfect stories through pure wrestling ability. The Montreal Screwjob incident in 1997 became one of wrestling's most controversial moments. Later had a successful run in WCW before injuries ended his career.",
                baseHp: 110,
                strength: 81,
                technicalAbility: 100,
                brawlingAbility: 89,
                stamina: 100,
                aerialAbility: 42,
                toughness: 92,
                moves: {
                    grapple: { name: "Snap Suplex", damage: { min: 11, max: 16 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "Russian Leg Sweep", damage: { min: 11, max: 17 }, baseHitChance: 0.8, stat: 'aerialAbility' },
                    highFlying: { name: "Backbreaker", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Sharpshooter", damage: { min: 24, max: 34 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Brock Lesnar",
                height: "6'3\"",
                weight: 286,
                description: "The Beast Incarnate! Dominant and destructive.",
                baseHp: 105,
                strength: 98,
                technicalAbility: 91,
                brawlingAbility: 88,
                stamina: 87,
                aerialAbility: 22,
                toughness: 95,
                moves: {
                    grapple: { name: "German Suplex", damage: { min: 12, max: 17 }, baseHitChance: 0.8, stat: 'strength' },
                    strike: { name: "Kimura Lock", damage: { min: 10, max: 18 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    highFlying: { name: "Knee Lift", damage: { min: 8, max: 14 }, baseHitChance: 0.6, stat: 'strength' },
                    finisher: { name: "F-5", damage: { min: 30, max: 36 }, baseHitChance: 0.92, stat: 'strength' }
                }
            },
            {
                name: "Bron Breakker",
                height: "6'0\"",
                weight: 232,
                description: "Son of Rick Steiner and nephew of Scott Steiner, representing the next generation of the Steiner wrestling dynasty. Two-time NXT Champion known for his incredible athleticism and power. His combination of amateur wrestling background and natural charisma has made him one of WWE's most promising young talents. His matches showcase both technical skill and explosive athleticism.",
                baseHp: 100,
                strength: 94,
                technicalAbility: 95,
                brawlingAbility: 87,
                stamina: 90,
                aerialAbility: 60,
                toughness: 92,
                moves: {
                    grapple: { name: "Military Press Slam", damage: { min: 12, max: 16 }, baseHitChance: 0.78, stat: 'strength' },
                    strike: { name: "Spear", damage: { min: 15, max: 20 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    highFlying: { name: "Frankensteiner", damage: { min: 14, max: 18 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    finisher: { name: "Steiner Recliner", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Bruiser Brody",
                height: "6'8\"",
                weight: 300,
                description: "One of the most feared and respected wrestlers in the world during the 1970s and 80s. Known for his wild, brawling style and refusal to follow traditional wrestling politics. Highly successful in Japan and Puerto Rico. His tragic murder in 1988 in Puerto Rico remains one of wrestling's darkest moments. His influence on hardcore wrestling and international wrestling culture was immense.",
                baseHp: 105,
                strength: 92,
                technicalAbility: 58,
                brawlingAbility: 96,
                stamina: 86,
                aerialAbility: 20,
                toughness: 94,
                moves: {
                    grapple: { name: "Running Big Boot", damage: { min: 14, max: 20 }, baseHitChance: 0.78, stat: 'brawlingAbility' },
                    strike: { name: "Elbow Drop", damage: { min: 9, max: 14 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    highFlying: { name: "Bearhug", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "King Kong Knee Drop", damage: { min: 27, max: 37 }, baseHitChance: 0.92, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Bruno Sammartino",
                height: "6'1\"",
                weight: 265,
                description: "The Living Legend. WWE Champion for over 11 years total across two reigns, the longest combined championship reign in company history. Sold out Madison Square Garden over 180 times. His Italian-American immigrant story resonated with audiences and helped build WWE's foundation. Known for incredible strength and his bearhug finishing hold. Posthumously inducted into the WWE Hall of Fame after years of being estranged from the company.",
                baseHp: 110,
                strength: 97,
                technicalAbility: 70,
                brawlingAbility: 92,
                stamina: 94,
                aerialAbility: 20,
                toughness: 96,
                moves: {
                    grapple: { name: "Bearhug", damage: { min: 15, max: 18 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Body Slam", damage: { min: 12, max: 18 }, baseHitChance: 0.8, stat: 'strength' },
                    highFlying: { name: "Hammer Lock", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    finisher: { name: "Backbreaker", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Christian",
                height: "6'2\"",
                weight: 230,
                description: "Captain Charisma. Two-time World Heavyweight Champion and multiple-time Intercontinental and tag team champion. Originally succeeded as part of Edge & Christian before establishing himself as a singles star. His \"peeps\" and various catchphrases showcased his natural comedic timing. Had career-defining runs in TNA Wrestling as a main event star. Known for his intelligence and psychology in crafting memorable matches.",
                baseHp: 100,
                strength: 85,
                technicalAbility: 93,
                brawlingAbility: 83,
                stamina: 90,
                aerialAbility: 80,
                toughness: 84,
                moves: {
                    grapple: { name: "Tornado DDT", damage: { min: 12, max: 18 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Frog Splash", damage: { min: 12, max: 17 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Spear", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Unprettier", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Chris Benoit",
                height: "5'11\"",
                weight: 220,
                description: "The Rabid Wolverine. Considered one of the greatest technical wrestlers ever, known for his intensity and submission expertise. World Heavyweight Champion and multiple-time Intercontinental Champion. His triple threat match at WrestleMania XX with Shawn Michaels and Triple H is considered a classic. His career accomplishments are overshadowed by the tragic events of 2007 that ended his life and the lives of his family members.",
                baseHp: 100,
                strength: 86,
                technicalAbility: 96,
                brawlingAbility: 89,
                stamina: 97,
                aerialAbility: 70,
                toughness: 95,
                moves: {
                    grapple: { name: "German Suplex", damage: { min: 12, max: 17 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "Diving Headbutt", damage: { min: 11, max: 19 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Snap Suplex", damage: { min: 10, max: 15 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    finisher: { name: "Crippler Crossface", damage: { min: 26, max: 36 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Chris Jericho",
                height: "6'0\"",
                weight: 225,
                description: "Y2J! The Ayatollah of Rock 'n' Rolla! First-ever Undisputed Champion, holding both WWE and WCW titles simultaneously. Master reinventer who has remained relevant across multiple decades and promotions. Known for his wit, catchphrases, and ability to get heat as a heel. Also successful as a rock musician with his band Fozzy. His recent work in AEW has introduced him to new generations of fans.",
                baseHp: 105,
                strength: 76,
                technicalAbility: 94,
                brawlingAbility: 82,
                stamina: 90,
                aerialAbility: 74,
                toughness: 84,
                moves: {
                    grapple: { name: "Codebreaker", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    strike: { name: "Lionsault", damage: { min: 12, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Diving Crossbody", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    finisher: { name: "Walls of Jericho", damage: { min: 23, max: 33 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "CM Punk",
                height: "6'2\"",
                weight: 218,
                description: "The Best in the World. Known for his 434-day WWE Championship reign and his famous \"pipe bomb\" promo in 2011. Master of both technical wrestling and microphone work. His straight-edge lifestyle became part of his character. Left WWE acrimoniously in 2014 but returned to wrestling in AEW and later back to WWE. Considered one of the most influential wrestlers of the 2000s.",
                baseHp: 100,
                strength: 81,
                technicalAbility: 93,
                brawlingAbility: 87,
                stamina: 92,
                aerialAbility: 71,
                toughness: 86,
                moves: {
                    grapple: { name: "Anaconda Vice", damage: { min: 12, max: 18 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Diving Elbow Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Springboard Clothesline", damage: { min: 11, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Go To Sleep", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Cody Rhodes",
                height: "6'2\"",
                weight: 220,
                description: "The American Nightmare. Son of Dusty Rhodes who carved his own path to success. Left WWE to help found AEW, where he became a main event star. Returned to WWE and won the Royal Rumble before capturing the WWE Championship at WrestleMania XL. His story of proving himself outside WWE and returning as a conquering hero resonated with fans worldwide.",
                baseHp: 100,
                strength: 87,
                technicalAbility: 94,
                brawlingAbility: 86,
                stamina: 93,
                aerialAbility: 68,
                toughness: 91,
                moves: {
                    grapple: { name: "Deathlock", damage: { min: 12, max: 7 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Alabama Slam", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    highFlying: { name: "Din's Fire", damage: { min: 11, max: 17 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    finisher: { name: "Cross Rhodes", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Curt Hennig",
                height: "6'3\"",
                weight: 257,
                description: "Mr. Perfect. One of the most naturally gifted athletes in wrestling history. Known for his perfect execution of moves and his cocky persona. Intercontinental Champion who elevated that title's prestige. Son of Larry \"The Axe\" Hennig and father of current wrestler Joe Hennig. His perfectplex finishing move and athletic vignettes made him one of the most memorable characters of the early 1990s.",
                baseHp: 105,
                strength: 86,
                technicalAbility: 98,
                brawlingAbility: 84,
                stamina: 96,
                aerialAbility: 77,
                toughness: 95,
                moves: {
                    grapple: { name: "Standing Dropkick", damage: { min: 9, max: 16 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "Swinging knee lift", damage: { min: 10, max: 17 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    highFlying: { name: "Figure-four leglock", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    finisher: { name: "Perfect-Plex", damage: { min: 26, max: 36 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Dan Spivey",
                height: "6'8\"",
                weight: 280,
                description: "Large, athletic wrestler who competed primarily in the 1980s and 1990s. Known for his size and surprising agility. Had runs in various promotions including WWE, WCW, and All Japan Pro Wrestling. Often competed as part of tag teams and was known for his dropkick despite his 6'7\" frame. His career spanned the transition from territorial wrestling to national promotion dominance.",
                baseHp: 100,
                strength: 94,
                technicalAbility: 76,
                brawlingAbility: 94,
                stamina: 85,
                aerialAbility: 45,
                toughness: 93,
                moves: {
                    grapple: { name: "Powerbomb", damage: { min: 13, max: 19 }, baseHitChance: 0.78, stat: 'strength' },
                    strike: { name: "Big Boot", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Spinebuster", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'strength' },
                    finisher: { name: "Spivey Spike (DDT)", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Daniel Bryan",
                height: "5'10\"",
                weight: 210,
                description: "The American Dragon. One of the most beloved wrestlers of the modern era. Known for his \"YES!\" chant that became a cultural phenomenon. Multiple-time WWE Champion whose WrestleMania XXX victory was one of the most emotional moments in wrestling history. Respected for his environmental activism and vegan lifestyle. His technical wrestling ability earned him comparisons to legends like Bret Hart.",
                baseHp: 100,
                strength: 80,
                technicalAbility: 97,
                brawlingAbility: 82,
                stamina: 94,
                aerialAbility: 89,
                toughness: 92,
                moves: {
                    grapple: { name: "Top Rope Hurricanrana", damage: { min: 11, max: 18 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "Busaiku Knee Kick", damage: { min: 11, max: 18 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Suicide Dive", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Yes Lock (Omoplata Crossface)", damage: { min: 25, max: 38 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Dean Malenko",
                height: "5'10\"",
                weight: 212,
                description: "The Man of 1,000 Holds. Considered one of the greatest technical wrestlers ever. Known for his stoic demeanor and incredible wrestling ability. Key member of The Radicalz stable when they jumped from WCW to WWE. His matches were wrestling clinics that showcased pure technical skill. Respected by peers as a wrestler's wrestler who could make anyone look good.",
                baseHp: 100,
                strength: 74,
                technicalAbility: 100,
                brawlingAbility: 76,
                stamina: 91,
                aerialAbility: 42,
                toughness: 83,
                moves: {
                    grapple: { name: "Suplex", damage: { min: 10, max: 15 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Springboard Dropkick", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Cross Armbreaker", damage: { min: 12, max: 19 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    finisher: { name: "Texas Cloverleaf", damage: { min: 25, max: 35 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Diamond Dallas Page",
                height: "6'5\"",
                weight: 260,
                description: "\"DDP\" - Self-made wrestler who didn't start his career until age 35. Three-time WCW World Heavyweight Champion known for his Diamond Cutter finishing move. Created the \"DDP Yoga\" fitness program that has helped numerous wrestlers and celebrities. His motivational story of starting late and achieving success inspired many. Known for his positive attitude and helping fellow wrestlers with addiction and health issues.",
                baseHp: 100,
                strength: 82,
                technicalAbility: 81,
                brawlingAbility: 92,
                stamina: 90,
                aerialAbility: 44,
                toughness: 89,
                moves: {
                    grapple: { name: "Fist Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Splash", damage: { min: 10, max: 15 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Inverted Atomic Drop", damage: { min: 9, max: 16 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    finisher: { name: "Diamond Cutter", damage: { min: 28, max: 38 }, baseHitChance: 0.92, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Don Muraco",
                height: "6'1\"",
                weight: 235,
                description: "The Magnificent Muraco. Two-time Intercontinental Champion. Known for his powerful physique and brawling style. Had memorable feuds with Pedro Morales and Jimmy Snuka. His segments with Jesse 'The Body' Ventura, including 'Fuji Vice', were comedy gold. Was the inaugural King of the Ring tournament winner. A consistent main event and upper mid-carder throughout the 80s in WWF.",
                baseHp: 100,
                strength: 92,
                technicalAbility: 70,
                brawlingAbility: 90,
                stamina: 85,
                aerialAbility: 30,
                toughness: 89,
                moves: {
                    grapple: { name: "Atomic Drop", damage: { min: 10, max: 15 }, baseHitChance: 0.75, stat: 'strength' },
                    strike: { name: "Piledriver", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    highFlying: { name: "DDT", damage: { min: 11, max: 16 }, baseHitChance: 0.72, stat: 'brawlingAbility' },
                    finisher: { name: "Asiatic Spike", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Dustin Rhodes",
                height: "6'6\"",
                weight: 260,
                description: "The Natural. Son of Dusty Rhodes who created one of wrestling's most unique characters in Goldust. Known for his androgynous, mind-games character that pushed boundaries in the 1990s. Multiple-time Intercontinental Champion with incredible longevity spanning over three decades. His ability to reinvent himself and remain relevant across different eras showcased his creativity and skill.",
                baseHp: 100,
                strength: 86,
                technicalAbility: 84,
                brawlingAbility: 91,
                stamina: 90,
                aerialAbility: 48,
                toughness: 96,
                moves: {
                    grapple: { name: "Bulldog", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    strike: { name: "Shattered Dreams (low blow)", damage: { min: 12, max: 18 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    highFlying: { name: "Running Uppercut", damage: { min: 11, max: 15 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Cross Rhodes", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Dusty Rhodes",
                height: "6'2\"",
                weight: 275,
                description: "The American Dream. One of wrestling's greatest talkers and most beloved characters. Three-time NWA World Heavyweight Champion known for his \"common man\" persona. His feuds with The Four Horsemen were legendary. Later became one of wrestling's most respected bookers and trainers. Father to Cody and Dustin Rhodes, his influence on wrestling storytelling cannot be overstated.",
                baseHp: 105,
                strength: 88,
                technicalAbility: 62,
                brawlingAbility: 94,
                stamina: 90,
                aerialAbility: 26,
                toughness: 91,
                moves: {
                    grapple: { name: "Elbow Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Atomic Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    highFlying: { name: "Bionic Knee Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Bionic Elbow", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Edge",
                height: "6'5\"",
                weight: 241,
                description: "The Rated-R Superstar. 11-time World Champion known for his opportunistic heel character. Master of ladder matches and hardcore wrestling. His surprise returns, particularly after career-threatening neck injuries, were some of wrestling's most emotional moments. Successful tag team career with Christian before establishing himself as a main event singles star. His spear finishing move and \"You think you know me\" entrance were iconic.",
                baseHp: 100,
                strength: 84,
                technicalAbility: 90,
                brawlingAbility: 90,
                stamina: 93,
                aerialAbility: 80,
                toughness: 91,
                moves: {
                    grapple: { name: "Edgecution", damage: { min: 12, max: 18 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Flying Crossbody", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Impailer DDT", damage: { min: 11, max: 17 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    finisher: { name: "Spear", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Eddie Guerrero",
                height: "5'8\"",
                weight: 220,
                description: "Latino Heat! WWE Champion known for his incredible charisma and technical ability. Famous for \"lying, cheating, and stealing\" while remaining a beloved babyface. His matches with Rey Mysterio, Chris Benoit, and Kurt Angle are considered classics. Member of The Radicalz. His death in 2005 at age 38 was one of wrestling's greatest losses. His family legacy continues through various relatives in the business.",
                baseHp: 100,
                strength: 80,
                technicalAbility: 90,
                brawlingAbility: 81,
                stamina: 91,
                aerialAbility: 95,
                toughness: 88,
                moves: {
                    grapple: { name: "Three Amigos", damage: { min: 12, max: 17 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "European Uppercut", damage: { min: 11, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Hurricanrana", damage: { min: 9, max: 17 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    finisher: { name: "Frog Splash", damage: { min: 26, max: 36 }, baseHitChance: 0.92, stat: 'aerialAbility' }
                }
            },
            {
                name: "Goldberg",
                height: "6'4\"",
                weight: 285,
                description: "Who's Next?! Former NFL player who became WCW's biggest homegrown star during the Monday Night Wars. Known for his incredible undefeated streak and dominant squash matches. His spear and jackhammer finishing moves were devastatingly effective. Universal Champion in WWE and WCW World Heavyweight Champion.",
                baseHp: 110,
                strength: 99,
                technicalAbility: 50,
                brawlingAbility: 97,
                stamina: 76,
                aerialAbility: 20,
                toughness: 96,
                moves: {
                    grapple: { name: "Spear", damage: { min: 15, max: 20 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Big Boot", damage: { min: 12, max: 17 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    highFlying: { name: "Clothesline", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    finisher: { name: "Jackhammer", damage: { min: 30, max: 38 }, baseHitChance: 0.92, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Gunther",
                height: "6'4\"",
                weight: 297,
                description: "The Ring General. Austrian powerhouse known for his hard-hitting European style. Longest-reigning Intercontinental Champion in modern WWE history. His matches are physical affairs that showcase old-school wrestling psychology. Known for his chops and powerbomb finishing move. Leader of Imperium stable and considered one of WWE's most dominant champions in recent years.",
                baseHp: getRandomInt(100, 115),
                strength: getRandomInt(90, 100),
                technicalAbility: getRandomInt(80, 95),
                brawlingAbility: getRandomInt(85, 100),
                stamina: getRandomInt(80, 95),
                aerialAbility: getRandomInt(10, 25),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Powerbomb", damage: { min: 15, max: 20 }, baseHitChance: 0.8, stat: 'strength' },
                    strike: { name: "Chop!", damage: { min: 14, max: 18 }, baseHitChance: 0.9, stat: 'brawlingAbility' },
                    highFlying: { name: "Dropkick", damage: { min: 10, max: 16 }, baseHitChance: 0.65, stat: 'strength' },
                    finisher: { name: "Sleeper Hold", damage: { min: 28, max: 38 }, baseHitChance: 0.92, stat: 'technicalAbility' }
                }
            },
            {
                name: "Hacksaw Jim Duggan",
                height: "6'3\"",
                weight: 280,
                description: "Hoooo! The patriotic American hero, known for carrying a 2x4 piece of wood and chanting 'U-S-A!'. Winner of the first Royal Rumble. A popular babyface whose simple, effective offense and unwavering patriotism made him a fan favorite throughout his career in WWF and WCW. His iconic 'Hoooo!' battle cry resonated with audiences worldwide.",
                baseHp: 100,
                strength: 93,
                technicalAbility: 55,
                brawlingAbility: 92,
                stamina: 80,
                aerialAbility: 20,
                toughness: 90,
                moves: {
                    grapple: { name: "Body Slam", damage: { min: 10, max: 15 }, baseHitChance: 0.75, stat: 'strength' },
                    strike: { name: "Old Glory", damage: { min: 12, max: 16 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    highFlying: { name: "Three Point Stance Tackle", damage: { min: 11, max: 16 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    finisher: { name: "Three-Point Stance Clothesline", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Harley Race",
                height: "6'1\"",
                weight: 245,
                description: "The King. Eight-time NWA World Champion.",
                baseHp: 105,
                strength: 85,
                technicalAbility: 87,
                brawlingAbility: 94,
                stamina: 93,
                aerialAbility: 28,
                toughness: 92,
                moves: {
                    grapple: { name: "Knee Drop", damage: { min: 12, max: 18 }, baseHitChance: 0.78, stat: 'brawlingAbility' },
                    strike: { name: "Headbutt", damage: { min: 10, max: 16 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    highFlying: { name: "Vertical Suplex", damage: { min: 13, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Piledriver", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Hulk Hogan",
                height: "6'8\"",
                weight: 302,
                description: "The Immortal, Hollywood Hulk Hogan! The most recognizable wrestler in history and the face of wrestling's mainstream breakthrough in the 1980s. Six-time WWE Champion whose WrestleMania III match with Andre the Giant was wrestling's biggest moment. His heel turn as \"Hollywood Hogan\" in WCW was one of wrestling's most shocking moments. Despite controversies, his impact on popular culture and wrestling's growth cannot be denied.",
                baseHp: 110,
                strength: 98,
                technicalAbility: 64,
                brawlingAbility: 94,
                stamina: 81,
                aerialAbility: 30,
                toughness: 94,
                moves: {
                    grapple: { name: "Body Slam", damage: { min: 13, max: 16 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Big Boot", damage: { min: 12, max: 20 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    highFlying: { name: "Reverse Chinlock", damage: { min: 11, max: 17 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    finisher: { name: "Leg Drop", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Ivan Koloff",
                height: "5'11\"",
                weight: 245,
                description: "The Russian Bear. A brutal and unforgiving powerhouse.",
                baseHp: getRandomInt(95, 110),
                strength: getRandomInt(85, 95),
                technicalAbility: getRandomInt(50, 70),
                brawlingAbility: getRandomInt(90, 100),
                stamina: getRandomInt(75, 90),
                aerialAbility: getRandomInt(5, 20),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Bearhug", damage: { min: 14, max: 20 }, baseHitChance: 0.78, stat: 'strength' },
                    strike: { name: "Knee Drop", damage: { min: 9, max: 14 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    highFlying: { name: "Belly-to-Belly Suplex", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Russian Sickle (Clothesline)", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Jeff Jarrett",
                height: "6'0\"",
                weight: 230,
                description: "Double J, J-E-Double F J-A-Double R-E-Double T! One of the most accomplished wrestlers in history, holding numerous championships across WCW, WWE, TNA, and AAA. Known for his country music rockstar gimmick and his guitar smash. Co-founded TNA Wrestling. Later became a successful promoter and producer, continuing to influence the industry behind the scenes.",
                baseHp: 100,
                strength: 78,
                technicalAbility: 92,
                brawlingAbility: 82,
                stamina: 89,
                aerialAbility: 60,
                toughness: 85,
                moves: {
                    grapple: { name: "Figure-Four Leglock", damage: { min: 12, max: 17 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Dropkick", damage: { min: 10, max: 15 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Swinging Neckbreaker", damage: { min: 9, max: 17 }, baseHitChance: 0.65, stat: 'technicalAbility' },
                    finisher: { name: "The Stroke", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Jake Roberts",
                height: "6'2\"",
                weight: 249,
                description: "The Snake. Master of mind games and DDTs.",
                baseHp: getRandomInt(85, 100),
                strength: getRandomInt(65, 80),
                technicalAbility: getRandomInt(75, 90),
                brawlingAbility: getRandomInt(70, 85),
                stamina: getRandomInt(70, 85),
                aerialAbility: getRandomInt(20, 40),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Short-Arm Clothesline", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Knee Lift", damage: { min: 8, max: 13 }, baseHitChance: 0.6, stat: 'technicalAbility' },
                    highFlying: { name: "Piledriver", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    finisher: { name: "DDT", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Jimmy Snuka",
                height: "6'0\"",
                weight: 250,
                description: "The Superfly. Pioneering high-flyer known for his breathtaking dives from the top rope. His leap off the top of a steel cage at Madison Square Garden in 1983 is one of wrestling's most iconic moments, inspiring a generation of wrestlers. Hall of Famer who had memorable feuds with Bob Backlund and Don Muraco. Despite legal controversies later in life, his in-ring innovation left an undeniable mark on the sport.",
                baseHp: 95,
                strength: 78,
                technicalAbility: 70,
                brawlingAbility: 85,
                stamina: 88,
                aerialAbility: 98,
                toughness: 80,
                moves: {
                    grapple: { name: "Headbutt", damage: { min: 10, max: 15 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    strike: { name: "Chop!", damage: { min: 12, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Flying Crossbody", damage: { min: 15, max: 20 }, baseHitChance: 0.85, stat: 'aerialAbility' },
                    finisher: { name: "Superfly Splash", damage: { min: 28, max: 38 }, baseHitChance: 0.95, stat: 'aerialAbility' }
                }
            },
            {
                name: "John Cena",
                height: "6'1\"",
                weight: 251,
                description: "17-time World Champion, the most in wrestling history. The face of WWE for over a decade and one of wrestling's biggest mainstream stars. Known for his \"Never Give Up\" motto and his Five Knuckle Shuffle. Successful Hollywood actor and philanthropist who has granted over 650 Make-A-Wish requests. His polarizing character sparked \"Let's Go Cena/Cena Sucks\" chants that defined an era.",
                baseHp: 105,
                strength: 93,
                technicalAbility: 76,
                brawlingAbility: 92,
                stamina: 88,
                aerialAbility: 42,
                toughness: 89,
                moves: {
                    grapple: { name: "Five Knuckle Shuffle", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Diving Leg Drop Bulldog", damage: { min: 11, max: 17 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Spinning Powerbomb", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Attitude Adjustment", damage: { min: 25, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Junkyard Dog",
                height: "6'3\"",
                weight: 280,
                description: "Woof woof! The charismatic and powerful JYD.",
                baseHp: getRandomInt(95, 110),
                strength: getRandomInt(80, 95),
                technicalAbility: getRandomInt(50, 70),
                brawlingAbility: getRandomInt(85, 100),
                stamina: getRandomInt(75, 90),
                aerialAbility: getRandomInt(10, 30),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Thump (headbutt)", damage: { min: 12, max: 18 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "Big Punch", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Body Slam", damage: { min: 9, max: 14 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Powerslam", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Kane",
                height: "6'10\"",
                weight: 323,
                description: "The Big Red Machine. The Undertaker's storyline brother known for his masked monster character. Mayor of Knox County, Tennessee, showing his intelligence beyond wrestling. Multiple-time World Champion with incredible longevity spanning over two decades. His debut at Hell in a Cell 1997 is one of wrestling's most memorable moments. Secondary image for demonstration purposes: https://placehold.co/150x150/1a1a1a/fff?text=Kane",
                baseHp: 105,
                strength: 96,
                technicalAbility: 68,
                brawlingAbility: 95,
                stamina: 90,
                aerialAbility: 41,
                toughness: 94,
                moves: {
                    grapple: { name: "Big Boot", damage: { min: 11, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Flying Clothesline", damage: { min: 12, max: 18 }, baseHitChance: 0.65, stat: 'strength' },
                    highFlying: { name: "Top Rope Clothesline", damage: { min: 11, max: 19 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Chokeslam", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Kerry Von Erich",
                height: "6'2\"",
                weight: 250,
                description: "The Modern Day Warrior. A powerful and athletic Von Erich.",
                baseHp: getRandomInt(90, 105),
                strength: getRandomInt(80, 95),
                technicalAbility: getRandomInt(60, 80),
                brawlingAbility: getRandomInt(75, 90),
                stamina: getRandomInt(75, 90),
                aerialAbility: getRandomInt(40, 60),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Flying Crossbody", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'aerialAbility' },
                    strike: { name: "Discus Punch", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Backhand Chop", damage: { min: 8, max: 13 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Iron Claw", damage: { min: 24, max: 34 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Kevin Nash",
                height: "7'0\"",
                weight: 320,
                description: "Big Sexy. 7-foot giant who was surprisingly agile and charismatic. Five-time World Champion across WWE and WCW. Founding member of the nWo, which revolutionized wrestling storylines. His powerbomb was one of wrestling's most devastating finishers. Later became successful backstage as a booker and creative consultant. Known for his wit and intelligence outside the ring.",
                baseHp: 100,
                strength: 95,
                technicalAbility: 65,
                brawlingAbility: 92,
                stamina: 83,
                aerialAbility: 20,
                toughness: 94,
                moves: {
                    grapple: { name: "Sidewalk Slam", damage: { min: 10, max: 17 }, baseHitChance: 0.6, stat: 'strength' },
                    strike: { name: "Big Boot", damage: { min: 12, max: 17 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Snake Eyes", damage: { min: 9, max: 15 }, baseHitChance: 0.65, stat: 'brawlingAbility' },
                    finisher: { name: "Jackknife Powerbomb", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Kevin Owens",
                height: "6'0\"",
                weight: 266,
                description: "Fight Owens Fight! A prizefighter who does whatever it takes.",
                baseHp: getRandomInt(95, 110),
                strength: getRandomInt(80, 95),
                technicalAbility: getRandomInt(70, 85),
                brawlingAbility: getRandomInt(85, 100),
                stamina: getRandomInt(75, 90),
                aerialAbility: getRandomInt(30, 50),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Cannonball", damage: { min: 12, max: 18 }, baseHitChance: 0.78, stat: 'brawlingAbility' },
                    strike: { name: "Senton", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Swanton Bomb", damage: { min: 11, max: 17 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Pop-up Powerbomb", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Kevin Von Erich",
                height: "6'2\"",
                weight: 220,
                description: "The Texas Tornado. A dynamic and athletic member of the legendary Von Erich family, known for his signature Iron Claw.",
                baseHp: getRandomInt(88, 103),
                strength: getRandomInt(75, 90),
                technicalAbility: getRandomInt(70, 85),
                brawlingAbility: getRandomInt(70, 85),
                stamina: getRandomInt(80, 95),
                aerialAbility: getRandomInt(50, 70),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Iron Claw (hold)", damage: { min: 10, max: 16 }, baseHitChance: 0.80, stat: 'strength' },
                    strike: { name: "Dropkick", damage: { min: 9, max: 14 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Flying Crossbody", damage: { min: 11, max: 17 }, baseHitChance: 0.70, stat: 'aerialAbility' },
                    finisher: { name: "Iron Claw", damage: { min: 26, max: 36 }, baseHitChance: 0.92, stat: 'strength' }
                }
            },
            {
                name: "Kurt Angle",
                height: "6'0\"",
                weight: 220,
                description: "It's true, it's damn true! Olympic gold medalist who became one of wrestling's greatest performers. Multiple-time WWE and World Heavyweight Champion known for his technical wrestling ability. His ankle lock submission hold was one of wrestling's most feared finishing moves. Successfully transitioned from amateur wrestling to professional wrestling faster than almost anyone.",
                baseHp: 105,
                strength: 89,
                technicalAbility: 97,
                brawlingAbility: 87,
                stamina: 95,
                aerialAbility: 50,
                toughness: 94,
                moves: {
                    grapple: { name: "Angle Slam", damage: { min: 12, max: 17 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Moonsault", damage: { min: 12, max: 17 }, baseHitChance: 0.79, stat: 'aerialAbility' },
                    highFlying: { name: "Top Rope Belly-to-Belly", damage: { min: 14, max: 19 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Ankle Lock", damage: { min: 24, max: 34 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Larry Zbyszko",
                height: "6'1\"",
                weight: 240,
                description: "The Living Legend. Technically gifted and a master of mind games.",
                baseHp: 100,
                strength: 76,
                technicalAbility: 92,
                brawlingAbility: 76,
                stamina: 89,
                aerialAbility: 25,
                toughness: 84,
                moves: {
                    grapple: { name: "Abdominal Stretch", damage: { min: 10, max: 14 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Piledriver", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    highFlying: { name: "Side Kick", damage: { min: 10, max: 15 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    finisher: { name: "Larryland Dreamer (Guillotine Choke)", damage: { min: 24, max: 34 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Lex Luger",
                height: "6'4\"",
                weight: 275,
                description: "The Total Package. Known for his incredible physique and his torture rack finishing move. Multiple-time World Champion in both WCW and brief runs in WWE. His patriotic character in WWE was an 'attempt to replace Hulk Hogan as the company's top babyface'. Had career-defining feuds with Sting and Hulk Hogan. His career was cut short by spinal cord injuries that left him temporarily paralyzed.",
                baseHp: 110,
                strength: 98,
                technicalAbility: 70,
                brawlingAbility: 93,
                stamina: 92,
                aerialAbility: 30,
                toughness: 94,
                moves: {
                    grapple: { name: "Bionic Forearm", damage: { min: 10, max: 18 }, baseHitChance: 0.8, stat: 'strength' },
                    strike: { name: "Clothesline", damage: { min: 11, max: 17 }, baseHitChance: 0.8, stat: 'strength' },
                    highFlying: { name: "Powerslam", damage: { min: 10, max: 17 }, baseHitChance: 0.8, stat: 'strength' },
                    finisher: { name: "Torture Rack", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Lord Steven Regal",
                height: "6'2\"",
                weight: 240,
                description: "The Gentleman Villain. Master technician known for his European wrestling style and his Regal Stretch submission hold. Multiple-time Intercontinental Champion and television champion who elevated every title he held. His matches were wrestling clinics that showcased pure technical skill and old-school psychology. Successfully transitioned to trainer and talent scout, helping develop future WWE stars at the Performance Center. His knowledge of wrestling fundamentals and ability to teach made him invaluable behind the scenes.",
                baseHp: 100,
                strength: 82,
                technicalAbility: 96,
                brawlingAbility: 85,
                stamina: 93,
                aerialAbility: 40,
                toughness: 90,
                moves: {
                    grapple: { name: "Regal Cutter", damage: { min: 10, max: 17 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "European uppercut", damage: { min: 12, max: 14 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    highFlying: { name: "Knee Trembler", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    finisher: { name: "Regal Stretch", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Luke Harper",
                height: "6'5\"",
                weight: 275,
                description: "The Exalted One. Powerful, agile, and intense.",
                baseHp: getRandomInt(95, 110),
                strength: getRandomInt(85, 95),
                technicalAbility: getRandomInt(65, 80),
                brawlingAbility: getRandomInt(80, 95),
                stamina: getRandomInt(75, 90),
                aerialAbility: getRandomInt(30, 50),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Discus Lariat", damage: { min: 15, max: 22 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    strike: { name: "Truck Stop (spinning side slam)", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'strength' },
                    highFlying: { name: "Big Boot", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Brodie Bomb (Running Senton)", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Macho Man Randy Savage",
                height: "6'1\"",
                weight: 237,
                description: "Ohhh Yeah! Two-time WWE Champion known for his flamboyant personality and colorful attire. His relationship with Miss Elizabeth was one of wrestling's greatest love stories. His flying elbow drop from the top rope was his signature finishing move. Master of the microphone with unforgettable catchphrases and promos. His match with Ricky Steamboat at WrestleMania III is considered one of the greatest ever.",
                baseHp: 105,
                strength: 89,
                technicalAbility: 84,
                brawlingAbility: 94,
                stamina: 95,
                aerialAbility: 90,
                toughness: 85,
                moves: {
                    grapple: { name: "Piledriver", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    strike: { name: "Axe Handle Drop", damage: { min: 13, max: 19 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    highFlying: { name: "Flying Crossbody", damage: { min: 11, max: 17 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Diving Elbow Drop", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'aerialAbility' }
                }
            },
            {
                name: "Mark Henry",
                height: "6'4\"",
                weight: 412,
                description: "The World's Strongest Man. An absolute powerhouse.",
                baseHp: 105,
                strength: 99,
                technicalAbility: 50,
                brawlingAbility: 90,
                stamina: 79,
                aerialAbility: 5,
                toughness: 89,
                moves: {
                    grapple: { name: "World's Strongest Slam", damage: { min: 12, max: 18 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Big Splash", damage: { min: 11, max: 16 }, baseHitChance: 0.75, stat: 'strength' },
                    highFlying: { name: "Press Slam", damage: { min: 12, max: 17 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Mark Henry's Powerslam", damage: { min: 30, max: 40 }, baseHitChance: 0.92, stat: 'strength' }
                }
            },
            {
                name: "Meng",
                height: "6'0\"",
                weight: 290,
                description: "The King of Tonga. Considered one of the most legitimately tough wrestlers in history, feared by his peers for his real-life street-fighting ability. Known for his Tongan Death Grip finishing move that was portrayed as a nerve hold. Multiple-time tag team champion both as part of The Islanders with Haku and solo runs. His reputation for being unbeatable in street fights made him one of wrestling's most respected figures backstage. Stories of his legendary toughness became part of wrestling folklore, with fellow wrestlers sharing tales of his incredible strength and pain tolerance.",
                baseHp: 105,
                strength: 94,
                technicalAbility: 75,
                brawlingAbility: 99,
                stamina: 82,
                aerialAbility: 64,
                toughness: 100,
                moves: {
                    grapple: { name: "Savate Kick", damage: { min: 13, max: 17 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "Asiatic Spike", damage: { min: 12, max: 16 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    highFlying: { name: "Flying Headbutt", damage: { min: 11, max: 17 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Tongan Death Grip", damage: { min: 25, max: 39 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Michael Hayes",
                height: "6'1\"",
                weight: 235,
                description: "The leader of the Fabulous Freebirds! A charismatic brawler and innovator.",
                baseHp: 90,
                strength: 75,
                technicalAbility: 65,
                brawlingAbility: 90,
                stamina: 78,
                aerialAbility: 40,
                toughness: 85,
                moves: {
                    grapple: { name: "DDT", damage: { min: 10, max: 17 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "Left Hand Punch", damage: { min: 12, max: 14 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    highFlying: { name: "Top Rope Missile Dropkick", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Freebird DDT", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Mick Foley",
                height: "6'2\"",
                weight: 287,
                description: "The Hardcore Legend. Three-time WWE Champion known for his hardcore wrestling style and multiple personalities (Mankind, Cactus Jack, Dude Love). His Hell in a Cell match with The Undertaker is one of wrestling's most brutal and memorable encounters. Successful author with multiple bestselling books. His ability to take punishment and tell compelling stories made him one of wrestling's most beloved figures. Known for his intelligence and articulate interviews outside the ring.",
                baseHp: 100,
                strength: 80,
                technicalAbility: 78,
                brawlingAbility: 94,
                stamina: 91,
                aerialAbility: 30,
                toughness: 100,
                moves: {
                    grapple: { name: "Double Arm DDT", damage: { min: 11, max: 17 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Elbow Drop (from apron)", damage: { min: 9, max: 16 }, baseHitChance: 0.65, stat: 'aerialAbility' },
                    highFlying: { name: "Running Knee", damage: { min: 8, max: 15 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Mandible Claw", damage: { min: 24, max: 34 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Mike Rotunda",
                height: "6'1\"",
                weight: 252,
                description: "Irwin R. Schyster (IRS). A calculating taxman who gets his due.",
                baseHp: getRandomInt(85, 100),
                strength: getRandomInt(70, 85),
                technicalAbility: getRandomInt(75, 90),
                brawlingAbility: getRandomInt(65, 80),
                stamina: getRandomInt(75, 90),
                aerialAbility: getRandomInt(10, 30),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Abdominal Stretch", damage: { min: 8, max: 13 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    strike: { name: "Clothesline", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Falling Clothesline", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'brawlingAbility' },
                    finisher: { name: "Write-Off (Stock Market Crash)", damage: { min: 24, max: 34 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Nick Bockwinkel",
                height: "6'1\"",
                weight: 235,
                description: "The Smartest Wrestler Alive. A scientific and articulate champion.",
                baseHp: 100,
                strength: 82,
                technicalAbility: 96,
                brawlingAbility: 74,
                stamina: 92,
                aerialAbility: 28,
                toughness: 86,
                moves: {
                    grapple: { name: "Piledriver", damage: { min: 12, max: 18 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Atomic Drop", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'strength' },
                    highFlying: { name: "Figure-Four Leglock", damage: { min: 14, max: 19 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    finisher: { name: "Bockwinkel's Sleeper", damage: { min: 26, max: 36 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Nikita Koloff",
                height: "6'2\"",
                weight: 275,
                description: "The Russian Nightmare. A powerful and intense Soviet-era wrestler.",
                baseHp: 100,
                strength: 92,
                technicalAbility: 74,
                brawlingAbility: 91,
                stamina: 88,
                aerialAbility: 24,
                toughness: 86,
                moves: {
                    grapple: { name: "Bearhug", damage: { min: 14, max: 17 }, baseHitChance: 0.8, stat: 'strength' },
                    strike: { name: "Russian Sickle (Clothesline)", damage: { min: 18, max: 25 }, baseHitChance: 0.9, stat: 'brawlingAbility' },
                    highFlying: { name: "Running Elbow Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.65, stat: 'brawlingAbility' },
                    finisher: { name: "Russian Sickle", damage: { min: 28, max: 38 }, baseHitChance: 0.92, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Omos",
                height: "7'3\"",
                weight: 403,
                description: "The Nigerian Giant. Imposing size and strength.",
                baseHp: getRandomInt(150, 170),
                strength: getRandomInt(98, 100),
                technicalAbility: getRandomInt(20, 40),
                brawlingAbility: getRandomInt(90, 100),
                stamina: getRandomInt(50, 65),
                aerialAbility: getRandomInt(1, 5),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Chokeslam", damage: { min: 14, max: 19 }, baseHitChance: 0.8, stat: 'strength' },
                    strike: { name: "Big Boot", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'strength' },
                    highFlying: { name: "Press Slam", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Two-Handed Chokeslam", damage: { min: 35, max: 45 }, baseHitChance: 0.92, stat: 'strength' }
                }
            },
            {
                name: "Owen Hart",
                height: "5'10\"",
                weight: 227,
                description: "The King of Harts. High-flying and technically gifted.",
                baseHp: getRandomInt(85, 100),
                strength: getRandomInt(70, 85),
                technicalAbility: getRandomInt(90, 100),
                brawlingAbility: getRandomInt(60, 80),
                stamina: getRandomInt(80, 95),
                aerialAbility: getRandomInt(85, 100),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Bridging German Suplex", damage: { min: 12, max: 18 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "Missile Dropkick", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Spinning Heel Kick", damage: { min: 11, max: 17 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    finisher: { name: "Sharpshooter", damage: { min: 26, max: 36 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Paul Orndorff",
                height: "6'0\"",
                weight: 249,
                description: "Mr. Wonderful. A charismatic and arrogant powerhouse. Main evented the first WrestleMania alongside Roddy Piper against Hulk Hogan and Mr. T. Known for his incredible physique and intense rivalries. Had a memorable run as Hulk Hogan's tag team partner before a bitter feud ensued. His piledriver was a feared finishing move. A key villain in the golden era of professional wrestling.",
                baseHp: 100,
                strength: 94,
                technicalAbility: 80,
                brawlingAbility: 90,
                stamina: 85,
                aerialAbility: 35,
                toughness: 92,
                moves: {
                    grapple: { name: "Full Nelson", damage: { min: 12, max: 17 }, baseHitChance: 0.78, stat: 'strength' },
                    strike: { name: "Forearm Smash", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Bodyslam", damage: { min: 9, max: 15 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Piledriver", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "R-Truth",
                height: "6'2\"",
                weight: 228,
                description: "Truth will set you free! Energetic and comedic, but can get serious.",
                baseHp: getRandomInt(80, 95),
                strength: getRandomInt(65, 80),
                technicalAbility: getRandomInt(70, 85),
                brawlingAbility: getRandomInt(70, 85),
                stamina: getRandomInt(80, 95),
                aerialAbility: getRandomInt(60, 75),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Lie Detector (Corkscrew Scissor Kick)", damage: { min: 12, max: 18 }, baseHitChance: 0.78, stat: 'aerialAbility' },
                    strike: { name: "Little Jimmy (jumping reverse STO)", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Flying Forearm", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'brawlingAbility' },
                    finisher: { name: "Axe Kick", damage: { min: 24, max: 34 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Randy Orton",
                height: "6'5\"",
                weight: 250,
                description: "The Viper. Apex Predator with a cunning mind.",
                baseHp: getRandomInt(90, 105),
                strength: getRandomInt(80, 95),
                technicalAbility: getRandomInt(70, 85),
                brawlingAbility: getRandomInt(80, 95),
                stamina: getRandomInt(85, 100),
                aerialAbility: getRandomInt(20, 40),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Punt Kick", damage: { min: 15, max: 22 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "DDT", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    highFlying: { name: "Powerslam", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "RKO", damage: { min: 28, max: 38 }, baseHitChance: 0.92, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Rey Mysterio",
                height: "5'6\"",
                weight: 175,
                description: "The Master of the 619! Master of lucha libre who revolutionized cruiserweight wrestling in America. World Heavyweight Champion despite his small stature, proving that size doesn't always matter. His 619 finishing move became one of wrestling's most popular signature moves. Multiple-time Intercontinental and tag team champion with incredible longevity. His matches with Eddie Guerrero are considered classics of storytelling and athleticism.",
                baseHp: 95,
                strength: 60,
                technicalAbility: 92,
                brawlingAbility: 81,
                stamina: 92,
                aerialAbility: 97,
                toughness: 80,
                moves: {
                    grapple: { name: "Springboard Crossbody", damage: { min: 10, max: 16 }, baseHitChance: 0.78, stat: 'aerialAbility' },
                    strike: { name: "West Coast Pop", damage: { min: 13, max: 19 }, baseHitChance: 0.8, stat: 'aerialAbility' },
                    highFlying: { name: "Hurricanrana", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    finisher: { name: "619", damage: { min: 25, max: 35 }, baseHitChance: 0.95, stat: 'aerialAbility' }
                }
            },
            {
                name: "Ric Flair",
                height: "6'1\"",
                weight: 243,
                description: "The Nature Boy! 16-time World Champion and arguably the greatest professional wrestler of all time. Known for his lavish lifestyle, custom suits, and \"Woo!\" catchphrase. Leader of The Four Horsemen, wrestling's most influential stable. His matches with Ricky Steamboat, Dusty Rhodes, and Sting are considered classics. Master of psychology and storytelling who could have a great match with anyone. His \"To be the man, you gotta beat the man\" promo is legendary.",
                baseHp: 110,
                strength: 77,
                technicalAbility: 95,
                brawlingAbility: 88,
                stamina: 100,
                aerialAbility: 35,
                toughness: 92,
                moves: {
                    grapple: { name: "Chop!", damage: { min: 11, max: 16 }, baseHitChance: 0.9, stat: 'brawlingAbility' },
                    strike: { name: "Knee Drop", damage: { min: 12, max: 17 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Suplex", damage: { min: 12, max: 17 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    finisher: { name: "Figure-Four Leglock", damage: { min: 20, max: 38 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Rick Rude",
                height: "6'3\"",
                weight: 252,
                description: "The Ravishing One. Arrogant and in incredible shape.",
                baseHp: getRandomInt(85, 100),
                strength: getRandomInt(75, 90),
                technicalAbility: getRandomInt(70, 85),
                brawlingAbility: getRandomInt(70, 85),
                stamina: getRandomInt(75, 90),
                aerialAbility: getRandomInt(20, 40),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Neckbreaker", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    strike: { name: "Piledriver", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    highFlying: { name: "Suplex", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'strength' },
                    finisher: { name: "Rude Awakening", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Rick Steiner",
                height: "5'11\"",
                weight: 250,
                description: "The Dog-Faced Gremlin. A powerful and aggressive amateur wrestling powerhouse.",
                baseHp: 100,
                strength: 93,
                technicalAbility: 86,
                brawlingAbility: 90,
                stamina: 88,
                aerialAbility: 29,
                toughness: 86,
                moves: {
                    grapple: { name: "Steinerline", damage: { min: 15, max: 22 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Bulldog", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Rebound Lariat", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    finisher: { name: "Steiner Driver", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Ricky Steamboat",
                height: "5'10\"",
                weight: 225,
                description: "The Dragon. Known for his incredible athleticism, high-flying maneuvers, and technical prowess.",
                baseHp: 105,
                strength: 85,
                technicalAbility: 95,
                brawlingAbility: 72,
                stamina: 100,
                aerialAbility: 86,
                toughness: 87,
                moves: {
                    grapple: { name: "Arm Drag", damage: { min: 10, max: 16 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "Chop", damage: { min: 12, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Diving Crossbody", damage: { min: 12, max: 18 }, baseHitChance: 0.85, stat: 'aerialAbility' },
                    finisher: { name: "Double Chicken Wing", damage: { min: 26, max: 36 }, baseHitChance: 0.92, stat: 'technicalAbility' }
                }
            },
            {
                name: "Road Warrior Animal",
                height: "6'2\"",
                weight: 280,
                description: "What a rush! The other half of the Legion of Doom.",
                baseHp: 105,
                strength: 95,
                technicalAbility: 55,
                brawlingAbility: 94,
                stamina: 80,
                aerialAbility: 40,
                toughness: 92,
                moves: {
                    grapple: { name: "Power Slam", damage: { min: 12, max: 18 }, baseHitChance: 0.82, stat: 'strength' },
                    strike: { name: "Clothesline", damage: { min: 14, max: 16 }, baseHitChance: 0.78, stat: 'brawlingAbility' },
                    highFlying: { name: "Elbow Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.6, stat: 'strength' },
                    finisher: { name: "Powerslam", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Road Warrior Hawk",
                height: "6'3\"",
                weight: 260,
                description: "Ohhhhh, what a rush! Half of the Legion of Doom.",
                baseHp: 108,
                strength: 91,
                technicalAbility: 42,
                brawlingAbility: 94,
                stamina: 82,
                aerialAbility: 66,
                toughness: 91,
                moves: {
                    grapple: { name: "Clothesline", damage: { min: 13, max: 18 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "Flying Shoulder Tackle", damage: { min: 10, max: 16 }, baseHitChance: 0.6, stat: 'strength' },
                    highFlying: { name: "Powerslam", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Diving Clothesline", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Rob Van Dam",
                height: "6'0\"",
                weight: 245,
                description: "Mr. Pay Per View! One of a kind high-flying innovator.",
                baseHp: 100,
                strength: 75,
                technicalAbility: 83,
                brawlingAbility: 87,
                stamina: 88,
                aerialAbility: 92,
                toughness: 81,
                moves: {
                    grapple: { name: "Rolling Thunder", damage: { min: 12, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    strike: { name: "Van Daminator (spinning heel kick)", damage: { min: 15, max: 19 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    highFlying: { name: "Split-Legged Moonsault", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Five-Star Frog Splash", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'aerialAbility' }
                }
            },
            {
                name: "Roddy Piper",
                height: "6'1\"",
                weight: 230,
                description: "Rowdy. One of the greatest talkers and most unpredictable.",
                baseHp: 100,
                strength: 83,
                technicalAbility: 81,
                brawlingAbility: 96,
                stamina: 90,
                aerialAbility: 20,
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Eye Poke", damage: { min: 5, max: 14 }, baseHitChance: 0.9, stat: 'brawlingAbility' },
                    strike: { name: "Punch Flurry", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Low Blow", damage: { min: 11, max: 16 }, baseHitChance: 0.65, stat: 'brawlingAbility' },
                    finisher: { name: "Sleeper Hold", damage: { min: 24, max: 34 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Roman Reigns",
                height: "6'3\"",
                weight: 265,
                description: "The Tribal Chief. Head of the Table and Undisputed.",
                baseHp: getRandomInt(100, 115),
                strength: getRandomInt(90, 100),
                technicalAbility: getRandomInt(60, 80),
                brawlingAbility: getRandomInt(85, 100),
                stamina: getRandomInt(90, 100),
                aerialAbility: getRandomInt(20, 40),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Superman Punch", damage: { min: 14, max: 18 }, baseHitChance: 0.78, stat: 'brawlingAbility' },
                    strike: { name: "Drive By (running dropkick)", damage: { min: 12, max: 16 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Samoan Drop", damage: { min: 13, max: 15 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Spear", damage: { min: 28, max: 38 }, baseHitChance: 0.92, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Ron Simmons",
                height: "6'2\"",
                weight: 270,
                description: "DAMN! Dominant and hard-hitting.",
                baseHp: 100,
                strength: 95,
                technicalAbility: 74,
                brawlingAbility: 93,
                stamina: 89,
                aerialAbility: 27,
                toughness: 94,
                moves: {
                    grapple: { name: "Spinebuster", damage: { min: 12, max: 18 }, baseHitChance: 0.78, stat: 'strength' },
                    strike: { name: "Powerbomb", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    highFlying: { name: "Clothesline", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'brawlingAbility' },
                    finisher: { name: "Dominator", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Samoa Joe",
                height: "6'2\"",
                weight: 282,
                description: "The Samoan Submission Machine. Brutal and dominant.",
                baseHp: 105,
                strength: 89,
                technicalAbility: 87,
                brawlingAbility: 94,
                stamina: 92,
                aerialAbility: 58,
                toughness: 96,
                moves: {
                    grapple: { name: "Muscle Buster", damage: { min: 13, max: 18 }, baseHitChance: 0.8, stat: 'strength' },
                    strike: { name: "Olé Kick", damage: { min: 12, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Diving Headbutt", damage: { min: 11, max: 17 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Coquina Clutch", damage: { min: 28, max: 38 }, baseHitChance: 0.92, stat: 'technicalAbility' }
                }
            },
            {
                name: "Scott Hall",
                height: "6'7\"",
                weight: 287,
                description: "The Bad Guy. Charismatic and cunning.",
                baseHp: 105,
                strength: 90,
                technicalAbility: 78,
                brawlingAbility: 88,
                stamina: 93,
                aerialAbility: 42,
                toughness: 89,
                moves: {
                    grapple: { name: "Bulldog", damage: { min: 10, max: 16 }, baseHitChance: 0.79, stat: 'brawlingAbility' },
                    strike: { name: "Chop!", damage: { min: 10, max: 15 }, baseHitChance: 0.78, stat: 'brawlingAbility' },
                    highFlying: { name: "Fallaway Slam", damage: { min: 14, max: 19 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Razor's Edge", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Scott Steiner",
                height: "6'1\"",
                weight: 276,
                description: "Big Poppa Pump. Genetically modified and mathematically superior.",
                baseHp: 100,
                strength: 97,
                technicalAbility: 84,
                brawlingAbility: 93,
                stamina: 92,
                aerialAbility: 58,
                toughness: 90,
                moves: {
                    grapple: { name: "Frankensteiner", damage: { min: 13, max: 19 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    strike: { name: "Diving Blockbuster", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    highFlying: { name: "Top Rope Steinerline", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Steiner Recliner", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Seth Rollins",
                height: "6'1\"",
                weight: 217,
                description: "The Architect. Visionary and revolutionary.",
                baseHp: getRandomInt(90, 105),
                strength: getRandomInt(75, 90),
                technicalAbility: getRandomInt(85, 100),
                brawlingAbility: getRandomInt(70, 85),
                stamina: getRandomInt(80, 95),
                aerialAbility: getRandomInt(70, 90),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Pedigree", damage: { min: 14, max: 20 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Frog Splash", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Phoenix Splash", damage: { min: 13, max: 19 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    finisher: { name: "Curb Stomp", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'aerialAbility' }
                }
            },
            {
                name: "Shawn Michaels",
                height: "6'1\"",
                weight: 225,
                description: "The Heartbreak Kid! Showstopper and highly agile performer.",
                baseHp: 110,
                strength: 76,
                technicalAbility: 92,
                brawlingAbility: 84,
                stamina: 100,
                aerialAbility: 89,
                toughness: 92,
                moves: {
                    grapple: { name: "Flying Forearm", damage: { min: 10, max: 16 }, baseHitChance: 0.78, stat: 'aerialAbility' },
                    strike: { name: "Diving Elbow Drop", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Teardrop Suplex", damage: { min: 10, max: 15 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    finisher: { name: "Sweet Chin Music", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Sheamus",
                height: "6'4\"",
                weight: 267,
                description: "The Celtic Warrior. A hard-hitting Irishman.",
                baseHp: getRandomInt(95, 110),
                strength: getRandomInt(85, 100),
                technicalAbility: getRandomInt(60, 80),
                brawlingAbility: getRandomInt(80, 95),
                stamina: getRandomInt(80, 95),
                aerialAbility: getRandomInt(20, 40),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Ten Beats of the Bodhran", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Irish Curse Backbreaker", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    highFlying: { name: "White Noise", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Brogue Kick", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Shinsuke Nakamura",
                height: "6'2\"",
                weight: 229,
                description: "The King of Strong Style. Charismatic striker.",
                baseHp: 100,
                strength: 84,
                technicalAbility: 89,
                brawlingAbility: 94,
                stamina: 91,
                aerialAbility: 56,
                toughness: 85,
                moves: {
                    grapple: { name: "Good Vibrations (corner stomps)", damage: { min: 10, max: 17 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Bomaye (running knee)", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Sliding German Suplex", damage: { min: 11, max: 17 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    finisher: { name: "Kinshasa", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Sid Vicious",
                height: "6'9\"",
                weight: 303,
                description: "The Master and Ruler of the World. Unstable powerhouse.",
                baseHp: getRandomInt(100, 115),
                strength: getRandomInt(90, 100),
                technicalAbility: getRandomInt(40, 60),
                brawlingAbility: getRandomInt(80, 95),
                stamina: getRandomInt(60, 75),
                aerialAbility: getRandomInt(5, 20),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Big Boot", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Leg Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.6, stat: 'strength' },
                    highFlying: { name: "Chokeslam", damage: { min: 15, max: 22 }, baseHitChance: 0.8, stat: 'strength' },
                    finisher: { name: "Powerbomb", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Stan Hansen",
                height: "6'4\"",
                weight: 290,
                description: "The Lariat. A wild and intense Texan brawler.",
                baseHp: 105,
                strength: 93,
                technicalAbility: 68,
                brawlingAbility: 98,
                stamina: 89,
                aerialAbility: 20,
                toughness: 97,
                moves: {
                    grapple: { name: "Piledriver", damage: { min: 15, max: 22 }, baseHitChance: 0.85, stat: 'brawlingAbility' },
                    strike: { name: "Elbow Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    highFlying: { name: "Belly-to-Back Suplex", damage: { min: 12, max: 18 }, baseHitChance: 0.8, stat: 'strength' },
                    finisher: { name: "Western Lariat", damage: { min: 30, max: 40 }, baseHitChance: 0.95, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Sting",
                height: "6'2\"",
                weight: 250,
                description: "The Icon! A dark and mysterious defender of justice.",
                baseHp: 105,
                strength: 92,
                technicalAbility: 84,
                brawlingAbility: 93,
                stamina: 98,
                aerialAbility: 78,
                toughness: 91,
                moves: {
                    grapple: { name: "Stinger Splash", damage: { min: 12, max: 16 }, baseHitChance: 0.95, stat: 'brawlingAbility' },
                    strike: { name: "Scorpion Deathdrop", damage: { min: 14, max: 20 }, baseHitChance: 0.9, stat: 'technicalAbility' },
                    highFlying: { name: "Top Rope Crossbody", damage: { min: 9, max: 14 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    finisher: { name: "Scorpion Deathlock", damage: { min: 25, max: 38 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Stone Cold Steve Austin",
                height: "6'2\"",
                weight: 252,
                description: "The Texas Rattlesnake! A beer-swilling, rule-breaking anti-hero.",
                baseHp: 105,
                strength: 88,
                technicalAbility: 76,
                brawlingAbility: 96,
                stamina: 91,
                aerialAbility: 25,
                toughness: 91,
                moves: {
                    grapple: { name: "Lou Thesz Press", damage: { min: 10, max: 15 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Mudhole Stomp", damage: { min: 8, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Bionic Elbow", damage: { min: 7, max: 13 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    finisher: { name: "Stone Cold Stunner", damage: { min: 26, max: 36 }, baseHitChance: 0.92, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Ted DiBiase",
                height: "6'3\"",
                weight: 249,
                description: "The Million Dollar Man. Everyone has a price!",
                baseHp: 100,
                strength: 78,
                technicalAbility: 90,
                brawlingAbility: 73,
                stamina: 91,
                aerialAbility: 24,
                toughness: 84,
                moves: {
                    grapple: { name: "Fist Drop", damage: { min: 9, max: 14 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    strike: { name: "Back Elbow", damage: { min: 8, max: 13 }, baseHitChance: 0.6, stat: 'brawlingAbility' },
                    highFlying: { name: "Hotshot", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'technicalAbility' },
                    finisher: { name: "Million Dollar Dream", damage: { min: 25, max: 35 }, baseHitChance: 0.95, stat: 'technicalAbility' }
                }
            },
            {
                name: "Terry Funk",
                height: "6'1\"",
                weight: 249,
                description: "The Funker. Hardcore legend with incredible longevity.",
                baseHp: 100,
                strength: 86,
                technicalAbility: 80,
                brawlingAbility: 97,
                stamina: 93,
                aerialAbility: 31,
                toughness: 98,
                moves: {
                    grapple: { name: "Spinning Toe Hold", damage: { min: 12, max: 17 }, baseHitChance: 0.8, stat: 'technicalAbility' },
                    strike: { name: "Taped Fist", damage: { min: 12, max: 17 }, baseHitChance: 0.78, stat: 'brawlingAbility' },
                    highFlying: { name: "Diving Moonsault", damage: { min: 10, max: 16 }, baseHitChance: 0.65, stat: 'aerialAbility' },
                    finisher: { name: "Texas Death Match Driver", damage: { min: 24, max: 38 }, baseHitChance: 0.9, stat: 'brawlingAbility' }
                }
            },
            {
                name: "Terry Gordy",
                height: "6'3\"",
                weight: 280,
                description: "One of the Fabulous Freebirds. A tough and hard-hitting brawler.",
                baseHp: 105,
                strength: 89,
                technicalAbility: 74,
                brawlingAbility: 92,
                stamina: 90,
                aerialAbility: 30,
                toughness: 93,
                moves: {
                    grapple: { name: "Lariat", damage: { min: 14, max: 18 }, baseHitChance: 0.78, stat: 'brawlingAbility' },
                    strike: { name: "Big Splash", damage: { min: 10, max: 16 }, baseHitChance: 0.6, stat: 'strength' },
                    highFlying: { name: "Powerslam", damage: { min: 12, max: 16 }, baseHitChance: 0.75, stat: 'strength' },
                    finisher: { name: "Powerbomb", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "The Big Show",
                height: "7'0\"",
                weight: 450,
                description: "The World's Largest Athlete. A giant among men.",
                baseHp: 110,
                strength: 100,
                technicalAbility: 45,
                brawlingAbility: 93,
                stamina: 77,
                aerialAbility: 26,
                toughness: 92,
                moves: {
                    grapple: { name: "Knockout Punch", damage: { min: 12, max: 20 }, baseHitChance: 0.8, stat: 'strength' },
                    strike: { name: "Banzai Drop", damage: { min: 10, max: 15 }, baseHitChance: 0.6, stat: 'strength' },
                    highFlying: { name: "Big Boot", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Showstopper (Chokeslam)", damage: { min: 30, max: 40 }, baseHitChance: 0.92, stat: 'strength' }
                }
            },
            {
                name: "The Great Khali",
                height: "7'1\"",
                weight: 347,
                description: "The Punjabi Nightmare. A towering giant with immense power and an intimidating presence.",
                baseHp: getRandomInt(130, 150),
                strength: getRandomInt(98, 100),
                technicalAbility: getRandomInt(20, 40),
                brawlingAbility: getRandomInt(90, 100),
                stamina: getRandomInt(50, 70),
                aerialAbility: getRandomInt(1, 5),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Vice Grip", damage: { min: 18, max: 25 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Punjabi Chop", damage: { min: 15, max: 22 }, baseHitChance: 0.80, stat: 'brawlingAbility' },
                    highFlying: { name: "Big Boot", damage: { min: 10, max: 16 }, baseHitChance: 0.60, stat: 'strength' },
                    finisher: { name: "Khali Bomb", damage: { min: 35, max: 45 }, baseHitChance: 0.90, stat: 'strength' }
                }
            },
            {
                name: "The Great Muta",
                height: "6'2\"",
                weight: 236,
                description: "The Essence of Muta. A mystical and dangerous Japanese legend.",
                baseHp: 100,
                strength: 81,
                technicalAbility: 86,
                brawlingAbility: 94,
                stamina: 92,
                aerialAbility: 90,
                toughness: 85,
                moves: {
                    grapple: { name: "Dragon Screw", damage: { min: 10, max: 16 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Handspring Elbow", damage: { min: 12, max: 18 }, baseHitChance: 0.75, stat: 'aerialAbility' },
                    highFlying: { name: "Flash Elbow", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    finisher: { name: "Moonsault", damage: { min: 28, max: 38 }, baseHitChance: 0.92, stat: 'aerialAbility' }
                }
            },
            {
                name: "The Miz",
                height: "6'2\"",
                weight: 221,
                description: "The A-Lister. Arrogant, cunning, and surprisingly resilient.",
                baseHp: getRandomInt(85, 100),
                strength: getRandomInt(65, 80),
                technicalAbility: getRandomInt(70, 85),
                brawlingAbility: getRandomInt(75, 90),
                stamina: getRandomInt(80, 95),
                aerialAbility: getRandomInt(30, 50),
                toughness: getRandomInt(60, 100),
                moves: {
                    grapple: { name: "Skull-Crushing Finale", damage: { min: 13, max: 19 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Reality Check (running knee lift)", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'brawlingAbility' },
                    highFlying: { name: "Awesome Clothesline", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'brawlingAbility' },
                    finisher: { name: "Figure-Four Leglock", damage: { min: 25, max: 35 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "The Rock",
                height: "6'4\"",
                weight: 270,
                description: "The Most Electrifying Man in Sports Entertainment! Charismatic and powerful.",
                baseHp: 105,
                strength: 90,
                technicalAbility: 78,
                brawlingAbility: 91,
                stamina: 90,
                aerialAbility: 35,
                toughness: 89,
                moves: {
                    grapple: { name: "Sharpshooter", damage: { min: 10, max: 16 }, baseHitChance: 0.78, stat: 'technicalAbility' },
                    strike: { name: "Samoan Drop", damage: { min: 12, max: 18 }, baseHitChance: 0.8, stat: 'strength' },
                    highFlying: { name: "Flying Crossbody", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'aerialAbility' },
                    finisher: { name: "People's Elbow", damage: { min: 22, max: 32 }, baseHitChance: 0.88, stat: 'brawlingAbility' }
                }
            },
            {
                name: "The Undertaker",
                height: "6'10\"",
                weight: 309,
                description: "The Deadman. A mystical force with unparalleled longevity.",
                baseHp: 115,
                strength: 92,
                technicalAbility: 71,
                brawlingAbility: 94,
                stamina: 84,
                aerialAbility: 39,
                toughness: 96,
                moves: {
                    grapple: { name: "Chokeslam", damage: { min: 13, max: 19 }, baseHitChance: 0.78, stat: 'strength' },
                    strike: { name: "Old School (Arm Walk)", damage: { min: 9, max: 14 }, baseHitChance: 0.65, stat: 'technicalAbility' },
                    highFlying: { name: "Flying Clothesline", damage: { min: 10, max: 16 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Tombstone Piledriver", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Triple H",
                height: "6'4\"",
                weight: 256,
                description: "The Game! The Cerebral Assassin! King of Kings.",
                baseHp: 105,
                strength: 92,
                technicalAbility: 88,
                brawlingAbility: 94,
                stamina: 91,
                aerialAbility: 28,
                toughness: 93,
                moves: {
                    grapple: { name: "Spinebuster", damage: { min: 12, max: 19 }, baseHitChance: 0.78, stat: 'strength' },
                    strike: { name: "Knee Drop", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'strength' },
                    highFlying: { name: "Facebreaker Knee Smash", damage: { min: 10, max: 16 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    finisher: { name: "Pedigree", damage: { min: 27, max: 37 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Tully Blanchard",
                height: "5'10\"",
                weight: 220,
                description: "The Brain Buster. An original member of the Four Horsemen, known for his cunning, aggression, and technical prowess. Held multiple championships, including the NWA National Heavyweight and US Heavyweight titles. Renowned for his feuds with Dusty Rhodes and Magnum T.A. and his tag team with Arn Anderson. Later became a successful manager, guiding many top stars. His sharp mind for wrestling psychology made him a formidable opponent and a respected figure.",
                baseHp: 98,
                strength: 75,
                technicalAbility: 90,
                brawlingAbility: 80,
                stamina: 88,
                aerialAbility: 35,
                toughness: 82,
                moves: {
                    grapple: { name: "Figure-Four Leglock", damage: { min: 10, max: 18 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    strike: { name: "Knee Drop", damage: { min: 9, max: 15 }, baseHitChance: 0.75, stat: 'brawlingAbility' },
                    highFlying: { name: "Piledriver", damage: { min: 12, max: 17 }, baseHitChance: 0.75, stat: 'technicalAbility' },
                    finisher: { name: "Slingshot Suplex", damage: { min: 26, max: 36 }, baseHitChance: 0.9, stat: 'technicalAbility' }
                }
            },
            {
                name: "Ultimate Warrior",
                height: "6'4\"",
                weight: 280,
                description: "Feel the power of the Warrior! Intense and energetic.",
                baseHp: 110,
                strength: 98,
                technicalAbility: 43,
                brawlingAbility: 90,
                stamina: 81,
                aerialAbility: 25,
                toughness: 90,
                moves: {
                    grapple: { name: "Gorilla Press Slam", damage: { min: 11, max: 19 }, baseHitChance: 0.85, stat: 'strength' },
                    strike: { name: "Flying Clothesline", damage: { min: 13, max: 17 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    highFlying: { name: "Big Splash", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Warrior Splash", damage: { min: 28, max: 38 }, baseHitChance: 0.9, stat: 'strength' }
                }
            },
            {
                name: "Vader",
                height: "6'5\"",
                weight: 350,
                description: "The Mastodon. A dominant super-heavyweight.",
                baseHp: 105,
                strength: 98,
                technicalAbility: 65,
                brawlingAbility: 96,
                stamina: 87,
                aerialAbility: 41,
                toughness: 96,
                moves: {
                    grapple: { name: "Fist Strikes", damage: { min: 13, max: 18 }, baseHitChance: 0.8, stat: 'brawlingAbility' },
                    strike: { name: "Moonsault (from top)", damage: { min: 12, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    highFlying: { name: "Chokeslam", damage: { min: 10, max: 18 }, baseHitChance: 0.7, stat: 'strength' },
                    finisher: { name: "Vader Bomb", damage: { min: 30, max: 40 }, baseHitChance: 0.92, stat: 'strength' }
                }
            },
        ];