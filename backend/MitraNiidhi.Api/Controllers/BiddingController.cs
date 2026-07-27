using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Bidding;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/groups/{groupId:int}/bidding")]
[Authorize]
public class BiddingController(IMediator mediator) : ControllerBase
{
    [HttpGet]
    [Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
    public async Task<IActionResult> Overview(int groupId, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetGroupBiddingQuery(groupId), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : NotFound(new { message = result.Error });
    }

    [HttpGet("months/{monthNumber:int}/bids")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
    public async Task<IActionResult> MonthBids(int groupId, int monthNumber, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetMonthBidsQuery(groupId, monthNumber), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("open")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Open(int groupId, [FromBody] OpenBiddingRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new OpenBiddingCommand(groupId, request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Bidding opened." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("months/{monthNumber:int}/close")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Close(int groupId, int monthNumber, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new CloseBiddingCommand(groupId, monthNumber), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Bidding closed." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("bids")]
    [Authorize(Roles = "Member")]
    public async Task<IActionResult> PlaceBid(int groupId, [FromBody] PlaceBidRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new PlaceBidCommand(groupId, request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Bid placed." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("approve-winner")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> ApproveWinner(int groupId, [FromBody] ApproveWinnerRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new ApproveWinnerCommand(groupId, request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Winner approved." }) : BadRequest(new { message = result.Error });
    }

    [HttpPost("allocate-organiser")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> AllocateOrganiser(int groupId, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new AllocateOrganiserMonthCommand(groupId), cancellationToken);
        return result.Succeeded
            ? Ok(new { message = "Month 1 pot assigned to organiser." })
            : BadRequest(new { message = result.Error });
    }
}

[ApiController]
[Route("api/groups/{groupId:int}/bc-chart")]
[Authorize]
public class BcChartController(IMediator mediator) : ControllerBase
{
    [HttpGet]
    [Authorize(Roles = "ClientAdmin,SuperAdmin,Member")]
    public async Task<IActionResult> Get(int groupId, CancellationToken ct)
    {
        var result = await mediator.Send(new GetGroupBcChartQuery(groupId), ct);
        return result.Succeeded ? Ok(result.Data) : NotFound(new { message = result.Error });
    }

    [HttpPut]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> Save(int groupId, [FromBody] SaveGroupBcChartRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new SaveGroupBcChartCommand(groupId, request), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("generate-defaults")]
    [Authorize(Roles = "ClientAdmin,SuperAdmin")]
    public async Task<IActionResult> GenerateDefaults(int groupId, CancellationToken ct)
    {
        var result = await mediator.Send(new GenerateDefaultBcChartCommand(groupId), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }
}
