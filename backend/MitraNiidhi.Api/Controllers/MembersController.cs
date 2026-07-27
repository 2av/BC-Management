using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Members;
using MitraNiidhi.Application.Notifications;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/[controller]")]
[Authorize(Roles = "Member")]
public class MembersController(IMediator mediator) : ControllerBase
{
    [HttpPost("me/push-token")]
    public async Task<IActionResult> RegisterPushToken([FromBody] RegisterPushTokenRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new RegisterPushTokenCommand(request), ct);
        return result.Succeeded ? Ok(new { message = "Push token registered." }) : BadRequest(new { message = result.Error });
    }

    [HttpDelete("me/push-token")]
    public async Task<IActionResult> UnregisterPushToken([FromQuery] string token, CancellationToken ct)
    {
        var result = await mediator.Send(new UnregisterPushTokenCommand(token ?? ""), ct);
        return result.Succeeded ? Ok(new { message = "Push token removed." }) : BadRequest(new { message = result.Error });
    }

    [HttpGet("me/dashboard")]
    public async Task<IActionResult> MyDashboard(CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetMemberDashboardQuery(), cancellationToken);
        if (!result.Succeeded)
            return BadRequest(new { message = result.Error });
        return Ok(result.Data);
    }

    [HttpGet("me/profile")]
    public async Task<IActionResult> MyProfile(CancellationToken ct)
    {
        var result = await mediator.Send(new GetMemberProfileQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPatch("me/profile")]
    public async Task<IActionResult> UpdateProfile([FromBody] UpdateMemberProfileRequest request, CancellationToken ct)
    {
        var result = await mediator.Send(new UpdateMemberProfileCommand(request), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("me/payment-options")]
    public async Task<IActionResult> PaymentOptions(CancellationToken ct)
    {
        var result = await mediator.Send(new GetPaymentOptionsQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("me/payment-methods")]
    public async Task<IActionResult> PaymentMethods(CancellationToken ct)
    {
        var result = await mediator.Send(new GetMemberPaymentMethodsQuery(), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpGet("me/payments/{groupId:int}/{month:int}")]
    public async Task<IActionResult> PaymentDetail(int groupId, int month, CancellationToken ct)
    {
        var result = await mediator.Send(new GetMemberPaymentDetailQuery(groupId, month), ct);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("me/payments/{groupId:int}/{month:int}/utr")]
    public async Task<IActionResult> SubmitUtr(
        int groupId,
        int month,
        [FromBody] SubmitPaymentUtrRequest request,
        CancellationToken ct)
    {
        var result = await mediator.Send(new SubmitPaymentUtrCommand(groupId, month, request), ct);
        return result.Succeeded
            ? Ok(new { message = "UTR submitted. Admin will confirm your payment." })
            : BadRequest(new { message = result.Error });
    }
}
