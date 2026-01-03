<?php
declare(strict_types=1);

namespace SagaManager\Admin;

/**
 * Entity Templates System
 *
 * Provides quick templates for common entity types to speed up creation.
 * Templates include predefined fields and structure based on entity type.
 *
 * @package SagaManager
 * @since 1.3.0
 */
class EntityTemplates {

	/**
	 * Get templates for specific entity type
	 *
	 * @param string $entity_type
	 * @return array
	 */
	public function get_templates_for_type( string $entity_type ): array {
		$all_templates = $this->get_all_templates();

		if ( ! isset( $all_templates[ $entity_type ] ) ) {
			return array();
		}

		return $all_templates[ $entity_type ];
	}

	/**
	 * Get all entity templates
	 *
	 * @return array
	 */
	private function get_all_templates(): array {
		return array(
			'character' => array(
				array(
					'id'          => 'character_basic',
					'name'        => __( 'Basic Character', 'saga-manager' ),
					'description' => __( 'Simple character template with essential fields', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_character_basic_template(),
						'importance'  => 50,
					),
				),
				array(
					'id'          => 'character_protagonist',
					'name'        => __( 'Protagonist', 'saga-manager' ),
					'description' => __( 'Main character template with detailed sections', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_character_protagonist_template(),
						'importance'  => 90,
					),
				),
				array(
					'id'          => 'character_antagonist',
					'name'        => __( 'Antagonist', 'saga-manager' ),
					'description' => __( 'Villain/antagonist template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_character_antagonist_template(),
						'importance'  => 85,
					),
				),
				array(
					'id'          => 'character_supporting',
					'name'        => __( 'Supporting Character', 'saga-manager' ),
					'description' => __( 'Secondary character template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_character_supporting_template(),
						'importance'  => 40,
					),
				),
			),

			'location'  => array(
				array(
					'id'          => 'location_basic',
					'name'        => __( 'Basic Location', 'saga-manager' ),
					'description' => __( 'Simple location template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_location_basic_template(),
						'importance'  => 50,
					),
				),
				array(
					'id'          => 'location_world',
					'name'        => __( 'World/Planet', 'saga-manager' ),
					'description' => __( 'Large-scale location template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_location_world_template(),
						'importance'  => 80,
					),
				),
				array(
					'id'          => 'location_settlement',
					'name'        => __( 'City/Settlement', 'saga-manager' ),
					'description' => __( 'Urban location template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_location_settlement_template(),
						'importance'  => 60,
					),
				),
			),

			'event'     => array(
				array(
					'id'          => 'event_basic',
					'name'        => __( 'Basic Event', 'saga-manager' ),
					'description' => __( 'Simple event template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_event_basic_template(),
						'importance'  => 50,
					),
				),
				array(
					'id'          => 'event_battle',
					'name'        => __( 'Battle/Conflict', 'saga-manager' ),
					'description' => __( 'Military engagement template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_event_battle_template(),
						'importance'  => 75,
					),
				),
				array(
					'id'          => 'event_political',
					'name'        => __( 'Political Event', 'saga-manager' ),
					'description' => __( 'Political/diplomatic event template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_event_political_template(),
						'importance'  => 65,
					),
				),
			),

			'faction'   => array(
				array(
					'id'          => 'faction_basic',
					'name'        => __( 'Basic Faction', 'saga-manager' ),
					'description' => __( 'Simple faction template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_faction_basic_template(),
						'importance'  => 50,
					),
				),
				array(
					'id'          => 'faction_government',
					'name'        => __( 'Government/Empire', 'saga-manager' ),
					'description' => __( 'Political power structure', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_faction_government_template(),
						'importance'  => 80,
					),
				),
				array(
					'id'          => 'faction_organization',
					'name'        => __( 'Organization', 'saga-manager' ),
					'description' => __( 'Group or organization template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_faction_organization_template(),
						'importance'  => 60,
					),
				),
			),

			'artifact'  => array(
				array(
					'id'          => 'artifact_basic',
					'name'        => __( 'Basic Artifact', 'saga-manager' ),
					'description' => __( 'Simple artifact template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_artifact_basic_template(),
						'importance'  => 50,
					),
				),
				array(
					'id'          => 'artifact_weapon',
					'name'        => __( 'Legendary Weapon', 'saga-manager' ),
					'description' => __( 'Powerful weapon template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_artifact_weapon_template(),
						'importance'  => 70,
					),
				),
			),

			'concept'   => array(
				array(
					'id'          => 'concept_basic',
					'name'        => __( 'Basic Concept', 'saga-manager' ),
					'description' => __( 'Simple concept template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_concept_basic_template(),
						'importance'  => 50,
					),
				),
				array(
					'id'          => 'concept_philosophy',
					'name'        => __( 'Philosophy/Belief', 'saga-manager' ),
					'description' => __( 'Belief system template', 'saga-manager' ),
					'fields'      => array(
						'description' => $this->get_concept_philosophy_template(),
						'importance'  => 65,
					),
				),
			),
		);
	}

	/*
	============================================
		Character Templates
		============================================ */

	private function get_character_basic_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Brief description of the character]</p>

<h2>Physical Description</h2>
<p>[Appearance, age, distinguishing features]</p>

<h2>Background</h2>
<p>[Brief history and origin]</p>

<h2>Role in Story</h2>
<p>[Character's purpose and significance]</p>
HTML;
	}

	private function get_character_protagonist_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Core identity and role]</p>

<h2>Physical Description</h2>
<p>[Detailed appearance]</p>

<h2>Background & History</h2>
<p>[Origin, upbringing, formative experiences]</p>

<h2>Personality</h2>
<ul>
<li><strong>Strengths:</strong> [Key strengths]</li>
<li><strong>Weaknesses:</strong> [Character flaws]</li>
<li><strong>Motivations:</strong> [What drives them]</li>
<li><strong>Fears:</strong> [What they fear]</li>
</ul>

<h2>Skills & Abilities</h2>
<p>[Special abilities, training, expertise]</p>

<h2>Character Arc</h2>
<p>[Development throughout the story]</p>

<h2>Relationships</h2>
<p>[Key connections to other characters]</p>
HTML;
	}

	private function get_character_antagonist_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Core identity and antagonistic role]</p>

<h2>Physical Description</h2>
<p>[Appearance and presence]</p>

<h2>Background</h2>
<p>[Origin of their villainy or opposition]</p>

<h2>Motivations</h2>
<p>[Why they oppose the protagonist - make them sympathetic]</p>

<h2>Methods & Resources</h2>
<p>[How they achieve their goals, allies, power base]</p>

<h2>Strengths & Weaknesses</h2>
<ul>
<li><strong>Strengths:</strong> [What makes them formidable]</li>
<li><strong>Weaknesses:</strong> [How they can be defeated]</li>
</ul>

<h2>Philosophy</h2>
<p>[Their worldview and beliefs]</p>
HTML;
	}

	private function get_character_supporting_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Who they are and their role]</p>

<h2>Description</h2>
<p>[Appearance and personality]</p>

<h2>Relationship to Main Characters</h2>
<p>[How they connect to the story]</p>

<h2>Contribution</h2>
<p>[What they bring to the narrative]</p>
HTML;
	}

	/*
	============================================
		Location Templates
		============================================ */

	private function get_location_basic_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Brief description of the location]</p>

<h2>Geography</h2>
<p>[Physical characteristics and layout]</p>

<h2>Significance</h2>
<p>[Why this location matters]</p>
HTML;
	}

	private function get_location_world_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Planet/world summary]</p>

<h2>Geography & Climate</h2>
<p>[Terrain, climate zones, notable features]</p>

<h2>Population</h2>
<p>[Inhabitants, cultures, civilizations]</p>

<h2>History</h2>
<p>[How the world came to be, major events]</p>

<h2>Strategic Importance</h2>
<p>[Why this world matters to the story]</p>

<h2>Notable Locations</h2>
<p>[Key cities, regions, landmarks]</p>
HTML;
	}

	private function get_location_settlement_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[City/settlement description]</p>

<h2>Geography & Layout</h2>
<p>[Physical location and urban structure]</p>

<h2>Population & Culture</h2>
<p>[Demographics, society, customs]</p>

<h2>Economy</h2>
<p>[Trade, industry, resources]</p>

<h2>Government</h2>
<p>[Who rules and how]</p>

<h2>Notable Features</h2>
<p>[Landmarks, districts, points of interest]</p>
HTML;
	}

	/*
	============================================
		Event Templates
		============================================ */

	private function get_event_basic_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[What happened]</p>

<h2>Date & Location</h2>
<p>[When and where it occurred]</p>

<h2>Participants</h2>
<p>[Who was involved]</p>

<h2>Outcome</h2>
<p>[Results and consequences]</p>

<h2>Significance</h2>
<p>[Why this event matters]</p>
HTML;
	}

	private function get_event_battle_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Battle summary]</p>

<h2>Date & Location</h2>
<p>[When and where]</p>

<h2>Opposing Forces</h2>
<ul>
<li><strong>Side A:</strong> [Forces, commanders, strength]</li>
<li><strong>Side B:</strong> [Forces, commanders, strength]</li>
</ul>

<h2>Strategic Context</h2>
<p>[Why the battle was fought]</p>

<h2>Course of Battle</h2>
<p>[How the engagement unfolded]</p>

<h2>Outcome</h2>
<ul>
<li><strong>Victor:</strong> [Who won]</li>
<li><strong>Casualties:</strong> [Losses on both sides]</li>
<li><strong>Aftermath:</strong> [Immediate results]</li>
</ul>

<h2>Long-term Consequences</h2>
<p>[How this battle changed the saga]</p>
HTML;
	}

	private function get_event_political_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Political event summary]</p>

<h2>Date & Location</h2>
<p>[When and where]</p>

<h2>Key Participants</h2>
<p>[Leaders, factions, nations involved]</p>

<h2>Context</h2>
<p>[Political situation leading to this event]</p>

<h2>Proceedings</h2>
<p>[What happened during the event]</p>

<h2>Outcome & Agreements</h2>
<p>[Results, treaties, decisions made]</p>

<h2>Impact</h2>
<p>[How this changed the political landscape]</p>
HTML;
	}

	/*
	============================================
		Faction Templates
		============================================ */

	private function get_faction_basic_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Faction description]</p>

<h2>Leadership</h2>
<p>[Who leads and how]</p>

<h2>Goals</h2>
<p>[What they want to achieve]</p>

<h2>Resources</h2>
<p>[Assets and capabilities]</p>

<h2>Relationships</h2>
<p>[Allies and enemies]</p>
HTML;
	}

	private function get_faction_government_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Government/empire summary]</p>

<h2>Structure</h2>
<p>[How the government is organized]</p>

<h2>Leadership</h2>
<p>[Ruler(s) and key officials]</p>

<h2>Territory</h2>
<p>[Lands controlled]</p>

<h2>Military</h2>
<p>[Armed forces and capabilities]</p>

<h2>Economy</h2>
<p>[Economic system and resources]</p>

<h2>Culture & Ideology</h2>
<p>[Beliefs and values]</p>

<h2>Foreign Relations</h2>
<p>[Relations with other powers]</p>
HTML;
	}

	private function get_faction_organization_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Organization description]</p>

<h2>Purpose</h2>
<p>[Why the organization exists]</p>

<h2>Structure</h2>
<p>[How it's organized]</p>

<h2>Membership</h2>
<p>[Who can join, how many members]</p>

<h2>Leadership</h2>
<p>[Who's in charge]</p>

<h2>Operations</h2>
<p>[What they do and how]</p>

<h2>Influence</h2>
<p>[Power and reach]</p>
HTML;
	}

	/*
	============================================
		Artifact Templates
		============================================ */

	private function get_artifact_basic_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Artifact description]</p>

<h2>Appearance</h2>
<p>[What it looks like]</p>

<h2>Properties</h2>
<p>[What it does, special abilities]</p>

<h2>History</h2>
<p>[Origin and past owners]</p>

<h2>Current Location</h2>
<p>[Where it is now]</p>
HTML;
	}

	private function get_artifact_weapon_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Legendary weapon summary]</p>

<h2>Appearance</h2>
<p>[Detailed description]</p>

<h2>Abilities</h2>
<ul>
<li><strong>Primary Power:</strong> [Main ability]</li>
<li><strong>Secondary Powers:</strong> [Additional abilities]</li>
<li><strong>Limitations:</strong> [Restrictions or costs]</li>
</ul>

<h2>Origin</h2>
<p>[How it was created, by whom]</p>

<h2>History</h2>
<p>[Famous wielders and deeds]</p>

<h2>Current Status</h2>
<p>[Where it is and who possesses it]</p>
HTML;
	}

	/*
	============================================
		Concept Templates
		============================================ */

	private function get_concept_basic_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Concept description]</p>

<h2>Definition</h2>
<p>[What this concept means]</p>

<h2>Significance</h2>
<p>[Why it matters to the saga]</p>

<h2>Examples</h2>
<p>[How it manifests in the story]</p>
HTML;
	}

	private function get_concept_philosophy_template(): string {
		return <<<'HTML'
<h2>Overview</h2>
<p>[Philosophy/belief system summary]</p>

<h2>Core Tenets</h2>
<p>[Main beliefs and principles]</p>

<h2>Practitioners</h2>
<p>[Who follows this philosophy]</p>

<h2>Practices</h2>
<p>[Rituals, customs, behaviors]</p>

<h2>History</h2>
<p>[Origin and development]</p>

<h2>Impact</h2>
<p>[How it influences the saga]</p>
HTML;
	}
}
