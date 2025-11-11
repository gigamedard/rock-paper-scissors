<?php
namespace Tests\Unit\Services;

use Tests\TestCase; 
use App\Models\User;
use App\Models\Fight;
use App\Models\PreMove;
use App\Models\FHist;
use App\Services\FightService;
use App\Services\HistoricalFightService;
use Mockery;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

// === Attributs d'isolation pour CE FICHIER ===
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class FightServiceWinTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handleFight_gere_correctement_une_victoire_rock_vs_scissors(): void
    {
        // 1. ARRANGEMENT
        $mockHistoryService = Mockery::mock(HistoricalFightService::class);
        
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($closure) { $closure(); });
            
        Mockery::mock('alias:' . FHist::class)
            ->shouldReceive('create')
            ->once(); 

        $builderMock = Mockery::mock(EloquentBuilder::class);
        $builderMock->shouldReceive('update')->once()->with(['status' => 'available']);
        
        $userMock = Mockery::mock('alias:' . User::class);
        $userMock->shouldReceive('where')->with(2)->andReturn($builderMock); // Perdant (User 2)

        $user1 = Mockery::mock(User::class);
        $user2 = Mockery::mock(User::class);
        $move1 = Mockery::mock(PreMove::class);
        $move2 = Mockery::mock(PreMove::class);

        $user1->id = 1;
        $user1->battle_balance = 100;
        $user1->preMove = $move1;
        $user1->shouldReceive('save')->once()->andReturn(true);

        $user2->id = 2;
        $user2->battle_balance = 100;
        $user2->preMove = $move2;
        $user2->shouldReceive('save')->once()->andReturn(true);

        $move1->moves = ['rock'];
        $move1->current_index = 0;
        $move1->shouldAllowMockingProtectedMethods();
        $move1->shouldReceive('increment')->once()->with('current_index'); 

        $move2->moves = ['scissors'];
        $move2->current_index = 0;
        $move2->shouldAllowMockingProtectedMethods();
        $move2->shouldReceive('increment')->once()->with('current_index');
        
        $fight = new Fight(['base_bet_amount' => 10, 'status' => 'waiting_for_result']);
        $fight->setRelation('user1', $user1);
        $fight->setRelation('user2', $user2);

        // 2. ACTION
        $fightService = new FightService($mockHistoryService);
        $fightService->handleFight($fight);

        // 3. ASSERTION
        $this->assertEquals('user1_win', $fight->result);
        $this->assertEquals(110, $user1->battle_balance);
        $this->assertEquals(90, $user2->battle_balance);
    }
}