using FluentAssertions;
using MitraNiidhi.Application.Common.Models;

namespace MitraNiidhi.Application.Tests;

public class ResultTests
{
    [Fact]
    public void Success_HasNoError()
    {
        var result = Result.Success();
        result.Succeeded.Should().BeTrue();
        result.Error.Should().BeNull();
    }

    [Fact]
    public void Failure_ExposesMessage()
    {
        var result = Result.Failure("nope");
        result.Succeeded.Should().BeFalse();
        result.Error.Should().Be("nope");
    }

    [Fact]
    public void GenericSuccess_CarriesData()
    {
        var result = Result<int>.Success(42);
        result.Succeeded.Should().BeTrue();
        result.Data.Should().Be(42);
    }

    [Fact]
    public void GenericFailure_HasNoData()
    {
        var result = Result<string>.Failure("missing");
        result.Succeeded.Should().BeFalse();
        result.Data.Should().BeNull();
        result.Error.Should().Be("missing");
    }
}
