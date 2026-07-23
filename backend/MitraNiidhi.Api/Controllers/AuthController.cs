using MediatR;
using Microsoft.AspNetCore.Authorization;
using Microsoft.AspNetCore.Mvc;
using MitraNiidhi.Application.Auth;
using MitraNiidhi.Domain.Enums;

namespace MitraNiidhi.Api.Controllers;

[ApiController]
[Route("api/[controller]")]
public class AuthController(IMediator mediator) : ControllerBase
{
    [HttpPost("login")]
    [AllowAnonymous]
    public async Task<IActionResult> Login([FromBody] LoginRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new LoginCommand(request), cancellationToken);
        if (!result.Succeeded)
            return Unauthorized(new { message = result.Error });

        return Ok(result.Data);
    }

    [HttpGet("me")]
    [Authorize]
    public IActionResult Me()
    {
        return Ok(new
        {
            id = User.FindFirst("sub")?.Value ?? User.FindFirst(System.Security.Claims.ClaimTypes.NameIdentifier)?.Value,
            username = User.Identity?.Name,
            fullName = User.FindFirst("full_name")?.Value,
            role = User.FindFirst("role")?.Value,
            clientId = User.FindFirst("client_id")?.Value
        });
    }

    [HttpGet("me/profile")]
    [Authorize]
    public async Task<IActionResult> MyProfile(CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new GetAdminProfileQuery(), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPatch("me/profile")]
    [Authorize]
    public async Task<IActionResult> UpdateProfile([FromBody] UpdateAdminProfileRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new UpdateAdminProfileCommand(request), cancellationToken);
        return result.Succeeded ? Ok(result.Data) : BadRequest(new { message = result.Error });
    }

    [HttpPost("change-password")]
    [Authorize]
    public async Task<IActionResult> ChangePassword([FromBody] ChangePasswordRequest request, CancellationToken cancellationToken)
    {
        var result = await mediator.Send(new ChangePasswordCommand(request), cancellationToken);
        return result.Succeeded ? Ok(new { message = "Password updated." }) : BadRequest(new { message = result.Error });
    }
}
